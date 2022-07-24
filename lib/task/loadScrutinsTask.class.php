<?php

class loadScrutinsTask extends sfBaseTask
{
  protected function configure()
  {
    $this->namespace = 'load';
    $this->name = 'Scrutins';
    $this->briefDescription = 'Load Scrutin data';
    $this->addOption('env', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'test');
    $this->addOption('app', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'frontend');
  }

  protected function execute($arguments = array(), $options = array())
  {
    $dir = dirname(__FILE__).'/../../batch/scrutin/scrutins/';
    $backupdir = dirname(__FILE__).'/../../batch/scrutin/loaded/';
    $manager = new sfDatabaseManager($this->configuration);
    $scrutins_sans_seance = 0;
    $seances_manquantes = 0;
    $seance_ids = array();

    $IGNORE_SEANCES = array(
      15 => array(
        220 => 1
      )
    );

    if (!is_dir($backupdir)) {
      mkdir($backupdir, 0777, TRUE);
    }

    if (is_dir($dir)) {
      foreach (scandir($dir) as $file) {
        if (!preg_match('/\.json$/', $file)) {
          continue;
        }

        echo "$dir$file\n";
        $json = file_get_contents($dir . $file);
        $data = json_decode($json);

        if (!$data) {
          echo "ERROR json : $data\n";
          continue;
        }

        $new = false;
        $scrutin = Doctrine::getTable('Scrutin')->findOneByNumero($data->numero);
        if (!$scrutin) {
          if (!$data->seance) {
            $scrutins_sans_seance++;
            continue;
          }
          $scrutin = new Scrutin();
          $scrutin->setNumero($data->numero);
          $scrutin->setType($data->type);
          $scrutin->setDate($data->date);
        }
        if (!$scrutin->seance_id) {
          try {
            $scrutin->setSeance($data->seance);
            $new = true;
          } catch (Exception $e) {
            // Commenté pour ne pas spammer les cron avec les séances pas encore publiées
            // echo "ERREUR $file (seance) : {$e->getMessage()}\n";
            $seances_manquantes++;
          }
        }

        if ($scrutin->seance_id && !in_array($scrutin->seance_id, $seance_ids)) {
          $seance_ids[] = $scrutin->seance_id;
        }

        try {
          $scrutin->setDemandeurs($data->demandeurs);
          $scrutin->setTitre($data->titre);
          $scrutin->setStats($data->sort,
                             $data->nombre_votants,
                             $data->nombre_pours,
                             $data->nombre_contres,
                             $data->nombre_abstentions);

        } catch(Exception $e) {
          echo "ERREUR $file (scrutin) : {$e->getMessage()}\n";
          continue;
        }

        if ($new) {
          try {
            $inter = $scrutin->tagIntervention();
            echo " -> http://www.nosdeputes.fr/".myTools::getLegislature()."/seance/$inter  \n";
          } catch(Exception $e) {
            echo "ERREUR $file (tag interventions) : {$e->getMessage()}\n";
            continue;
          }
        }

        $scrutin->save();

        $scrutin->setVotes($data->parlementaires, $data->nb_delegations);

        if ($scrutin->seance_id) {
          rename($dir . $file, $backupdir . $file);
        }

        $scrutin->free();
      }

      // Vérification des scrutins pour chaque séance modifiée
      $seances = Doctrine::getTable("Seance")
                         ->createQuery("s")
                         ->whereIn("s.id", $seance_ids)
                         ->andWhere("s.type = 'hemicycle'")
                         ->execute();

      foreach ($seances as $seance) {
        $scrutins = count($seance->Scrutins);
        $tables = Doctrine::getTable("Intervention")
                          ->createQuery("i")
                          ->select("count(1) as cnt")
                          ->where("i.seance_id = ?", $seance->id)
                          ->andWhere("i.intervention LIKE '%nombre de votants%suffrages exprimés%pour%contre%' OR i.intervention LIKE '%Majorité requise pour l\'adoption%pour l\'adoption%'")
                          // ->andWhere("i.intervention LIKE '%<table class=\"scrutin%'")
                          ->fetchOne()['cnt'];

        if ($scrutins != $tables && !isset($IGNORE_SEANCES[myTools::getLegislature()][$seance->id])) {
          $source = Doctrine::getTable("Intervention")
                         ->createQuery("i")
                         ->where("i.seance_id = ?", $seance->id)
                         ->andWhere("i.intervention LIKE '%nombre de votants%suffrages exprimés%pour%contre%' OR i.intervention LIKE '%Majorité requise pour l\'adoption%pour l\'adoption%'")
                         ->fetchOne();
          echo "WARNING: séance {$seance->id} https://www.nosdeputes.fr/".myTools::getLegislature()."/seance/{$seance->id} du {$seance->date} {$seance->moment} : {$scrutins} scrutins, {$tables} tableaux -> {$source->source}\n";
        }
      }

      if ($scrutins_sans_seance > 0)
        echo "WARNING: $scrutins_sans_seance scrutins sans séance dans l'OpenData\n";
      if ($seances_manquantes > 0)
        echo "WARNING: $seances_manquantes scrutins sans séance dans ND\n";
    }
  }
}
