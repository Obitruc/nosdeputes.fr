<?php $nResults = $pager->getNbResults(); ?>
<h1><?php echo $orga->getNom(); $sf_response->setTitle($orga->getNom()); ?></h1>
<?php include_component('article', 'show', array('categorie'=>'Organisme', 'object_id'=>$orga->id)); ?>
<?php if ($nResults) {if ($orga->type == 'extra') : ?>
<h2>Organisme extra-parlementaire composé de <?php echo $nResults; ?> député<?php if ($nResults > 1) echo 's'; ?>&nbsp;:</h2>
<?php else : ?>
<h2><?php if (preg_match('/commission/i', $orga->getNom())) echo 'Comm'; else echo 'M'; ?>ission parlementaire composée de <?php echo $nResults; ?> député<?php if ($nResults > 1) echo 's'; ?>&nbsp;:</h2>
<?php endif; }?>
<ul>
<?php foreach($pager->getResults() as $parlementaire) : ?>
<li><?php echo $parlementaire->getPOrganisme($orga->getNom())->getFonction(); ?> : <?php
echo link_to($parlementaire->nom, 'parlementaire/show?slug='.$parlementaire->slug); ?> (<?php
echo $parlementaire->getStatut(1).", ".link_to($parlementaire->nom_circo, '@list_parlementaires_circo?search='.$parlementaire->nom_circo); ?>)</li>
<?php endforeach ; ?>
</ul>
<?php include_partial('parlementaire/paginate', array('pager'=>$pager, 'link'=>'@list_parlementaires_organisme?slug='.$orga->getSlug().'&'));
if (count($seances) && ($pager->getPage() < 2) ) { ?>
<div><h3>Les dernières réunions de la <?php if (preg_match('/commission/i', $orga->getNom())) echo 'Comm'; else echo 'M'; ?>ission</h3>
<ul>
<?php $cpt = 0; foreach($seances as $seance) { $cpt++;?>
<li><?php $subtitre = $seance->getTitre();
  if ($seance->nb_commentaires > 0) {
    $subtitre .= ' ('.$seance->nb_commentaires.' commentaire';
    if ($seance->nb_commentaires > 1) $subtitre .= 's';
    $subtitre .= ')';
  }
  echo link_to($subtitre, '@interventions_seance?seance='.$seance->id); ?></li>
<?php if ($cpt > 40) break;} ?>
</ul>
</div>
<?php } ?>
