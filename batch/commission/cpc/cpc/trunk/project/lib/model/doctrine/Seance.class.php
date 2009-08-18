<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class Seance extends BaseSeance
{
  public function addPresence($parlementaire, $type, $source) {
    $q = Doctrine::getTable('Presence')->createQuery('p');
    $q->where('parlementaire_id = ?', $parlementaire->id)->andWhere('seance_id = ?', $this->id);
    $presence = $q->execute()->getFirst();
    $q->free();
    unset($q);
    if (!$presence) {
      $presence = new Presence();
      $presence->Parlementaire = $parlementaire;
      $presence->Seance = $this;
      $presence->save();
    }
    $res = $presence->addPreuve($type, $source);
    $presence->free();
    return $res;
  }
  
  public static function convertMoment($moment) {
    if (preg_match('`(seance|séance)`i', $moment)) {
        if (preg_match('`1`', $moment)) return "1ère séance";
        if (preg_match('`(\d{1})`', $moment, $match)) return $match[1];
        return $moment;
    }
    if (preg_match('`(reunion|réunion)`i', $moment)) {
        if (preg_match('`1`', $moment)) return "1ère réunion";
        if (preg_match('`(\d{1})`', $moment, $match)) return $match[1]."ème réunion";
        return $moment;
    }
    if (preg_match('/(\d{1,2})[:h](\d{2})/', $moment, $match)) {
      $moment = sprintf("%02d:%02d", $match[1], $match[2]);
      return $moment;
    }
    return $moment;
  }
  
  public function setDate($date) {
    if (!$this->_set('date', $date))
      return false;
    $date = strtotime($date);
    $this->_set('annee', date('Y', $date));
    $this->_set('numero_semaine', date('W', $date));
    return true;
  }
  public function getInterventions() {
    $q = doctrine::getTable('Intervention')->createQuery('i')->where('seance_id = ?', $this->id)->leftJoin('i.Personnalites p')->leftJoin('i.Parlementaires pa')->orderBy('i.timestamp ASC');
    return $q->execute();
  }
}