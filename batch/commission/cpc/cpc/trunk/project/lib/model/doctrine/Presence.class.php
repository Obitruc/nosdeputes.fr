<?php

/**
 * This class has been auto-generated by the Doctrine ORM Framework
 */
class Presence extends BasePresence
{
  public function addPreuve($type, $source) {
    $q = Doctrine::getTable('PreuvePresence')->createQuery('p');
    $preuve = $q->where('presence_id = ?', $this->id)->andWhere('type = ?', $type)->fetchOne();
    $q->free();
    if (!$preuve) {
      $preuve = new PreuvePresence();
      $preuve->presence_id = $this->id;
      $preuve->type = $type;
      $this->nb_preuves++ ;
    }
    $preuve->source = $source;
    $res = $preuve->save();
    $this->save();
    $preuve->free();
    return $res;
  }
}