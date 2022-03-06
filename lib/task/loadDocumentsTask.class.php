<?php

class loadDocumentsTask extends sfBaseTask {
  protected function configure() {
    $this->namespace = 'load';
    $this->name = 'Documents';
    $this->briefDescription = 'Load Documents data';
    $this->addOption('env', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'test');
    $this->addOption('app', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'frontend');
  }

  protected function execute($arguments = array(), $options = array()) {
    $dir = dirname(__FILE__).'/../../batch/documents/out/';
    $manager = new sfDatabaseManager($this->configuration);
    $doc = null;

    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) != false) {
          if ($file == ".." || $file == "." || $file == ".svn") continue;
          if ($doc) exit(1);
          foreach(file($dir.$file) as $line) {
            echo "$dir$file ... ";
            $json = json_decode($line);
            if (!$json) {
              echo "ERROR json : \n";
              continue;
            }
            if (!$json->source)
              {echo "ERROR source : \n"; continue;}
            if (!$json->legislature)
              {echo "ERROR legislature : \n"; continue;}
            if (!$json->id)
              {echo "ERROR id : \n"; continue;}
            if (!$json->numero)
              {echo "ERROR numero : \n"; continue;}
            if(!$json->date_depot)
              {echo "ERROR date_depot : \n"; continue;}
            if (!$json->type)
              {echo "ERROR type : \n"; continue;}
            $new = false;
            $doc = Doctrine::getTable('Texteloi')->find($json->id);
            if (!$doc) {
              $new = true;
              $doc = new Texteloi();
              $doc->id = $json->id;
              $doc->source = preg_replace('/https:/', 'http:', $json->source);
              $doc->legislature = $json->legislature;
              $doc->numero = $json->numero;
              if ($json->annexe != "") {
                if (Doctrine::getTable('Texteloi')->findByNumAnnexe($json->numero,$json->annexe))
                  {echo "ERROR numero + annexe déjà existant avec url différente : \n"; continue;}
                $doc->annexe = $json->annexe;
              }
              $doc->date = $json->date_depot;
              $doc->type = $json->type;
            }
            if (isset($json->date_publi) && $json->date_publi)
              $doc->date = $json->date_publi;
            $doc->setDossier($json->dossier);
            if ($json->type_details)
              $doc->type_details = $json->type_details;
            if ($json->titre)
              $doc->titre = $json->titre;
            if ($json->categorie)
              $doc->categorie = $json->categorie;
            if ($json->auteurs)
              $doc->setAuteurs($json->auteurs);
            if ($json->motscles)
              foreach (explode('.', $json->motscles) as $tag)
                if (strlen($tag) <= 50)
                  $doc->addTag($tag);
            if ($json->contenu)
              $doc->setContenu($json->contenu);
            $doc->save();
            $doc->free();
            if ($new) {
              $reindexWithParls = Doctrine::getTable('Texteloi')->find($json->id);
              $reindexWithParls->save();
            }
            echo "DONE\n";
          }
          unlink($dir.$file);
        }
        closedir($dh);
      }
    }
  }
}
