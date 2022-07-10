<?php

class sendAlertTask extends sfBaseTask
{
  protected function configure()
  {
    $this->namespace = 'send';
    $this->name = 'Alert';
    $this->briefDescription = 'send alerts';
    $this->addOption('env', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'prod');
    $this->addOption('app', null, sfCommandOption::PARAMETER_OPTIONAL, 'Changes the environment this task is run in', 'frontend');
    $this->addOption('verbose', null, sfCommandOption::PARAMETER_OPTIONAL, 'verbose (yes or no)', 'no');
    $this->addOption('test', null, sfCommandOption::PARAMETER_OPTIONAL, 'do not send mails nor update database (yes or no)', 'no');
  }

  protected static $period = array('HOUR' => 3600, 'DAY' => 86400, 'WEEK' => 604800, 'MONTH' => 2592000);

  protected function execute($arguments = array(), $options = array())
  {
    $this->configuration = sfProjectConfiguration::getApplicationConfiguration($options['app'], $options['env'], true);
    $manager = new sfDatabaseManager($this->configuration);
    $context = sfContext::createInstance($this->configuration);
    $this->configuration->loadHelpers(array('Partial', 'Url'));
    $verbose = ($options['verbose'] == 'yes');
    $test = ($options['test'] == 'yes');
    $bad_sections = Doctrine_Query::create()->select('id')->from('Section')->where('titre IS NULL OR titre LIKE "Ordre du jour%"')->fetchArray();
    $exclude_sections = array_map(function($v){ return '-id:Section/'.$v['id']; }, $bad_sections);
    $solr = new SolrConnector();
    $query = Doctrine::getTable('Alerte')->createQuery()->andWhere('(next_mail < NOW() OR next_mail IS NULL) AND confirmed = 1');
    foreach($query->execute() as $alerte) if (preg_match("/\w@\w/", $alerte->email)) {
      $currenttime = time();
      $date = strtotime(preg_replace('/ /', 'T', $alerte->last_mail)."Z")+1;
        $query = '('.$alerte->query.") ".join(" ", $exclude_sections)." date:[".date('Y-m-d', $date).'T'.date('H:i:s', $date)."Z TO ".date('Y-m-d', $currenttime).'T'.date('H:i:s', $currenttime)."Z]";
      foreach (explode('&', $alerte->filter) as $filtre)
        if (preg_match('/^([^=]+)=(.*)$/', $filtre, $match))
          foreach (explode(',', $match[2]) as $value) {
            if (preg_match("=", $match[2]))
              $query .= ' '.$match[1].':'.preg_replace('/=(.*)$/', '="$1"', $match[2]);
            else $query .= ' '.$match[1].':"'.$match[2].'"';
          }
      if ($alerte->no_human_query)
        $query .= " -object_name:Section";
      if ($verbose) {
	print "LOG: query for alerte ".$alerte->id." to ".$alerte->email.": $query\n";
      }
      $results = $solr->search($query, array('sort' => 'date desc', 'hl' => 'yes', 'hl.fragsize'=>500));
      $alerte->next_mail = date('Y-m-d H:i:s', $currenttime + self::$period[$alerte->period]);
      if (! $results['response']['numFound']) {
        if ($verbose) print "Save with no new result\n";
        if (!$test) $alerte->save();
        continue;
      }
      echo "sending mail to : ".$alerte->email."\n";
      echo $alerte->titre."\n";
      $text = get_partial('mail/sendAlerteTxt', array('alerte' => $alerte, 'results' => $results, 'nohuman' => $alerte->no_human_query));
      if ($test) {
        echo $text."\n";
        continue;
      }
      $message = $this->getMailer()->compose(array('contact@regardscitoyens.org' => '"Regards Citoyens"'),
					     $alerte->email,
					     '[NosDeputes.fr] Alerte - '.$alerte->titre);

      $message->setBody($text, 'text/plain');
      try {
        $this->getMailer()->send($message);
        if ($verbose) print "Save with results\n";
        $alerte->last_mail = preg_replace('/T/', ' ', preg_replace('/Z/', '', $results['response']['docs'][0]['date']));
        $alerte->save();
      } catch(Exception $e) {
        echo "ERROR: mail could not be sent ($text)\n";
      }
    }
  }
}
