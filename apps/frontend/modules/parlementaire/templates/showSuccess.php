<?php $sf_response->setTitle($parlementaire->nom.' - Son activité de député'.($parlementaire->sexe == "F" ? "e" : "").' à l\'Assemblée nationale - NosDéputés.fr'); ?>
<div class="fiche_depute">
  <div class="info_depute">
    <h1><?php echo $parlementaire->nom; ?></h1><h2>, <?php echo preg_replace('/(\d)(è[mr]e)/', '\\1<sup><small>\\2</small></sup>', $parlementaire->getLongStatut(1)); ?></h2>
<?php if ($parlementaire->url_nouveau_cpc) : ?>
  <h3 style="color:red; margin: 0px"><a href="<?php echo $parlementaire->url_nouveau_cpc; ?>"><?php echo $parlementaire->ceCette; ?> a été réélu<?php echo ($parlementaire->sexe == "F" ? "e" : ""); ?>, consultez sa fiche pour la <?php echo sfConfig::get('app_legislature')+1; ?><sup>ème</sup> législature</a></h3>
<?php endif; ?>
  </div>
  <div class="depute_gauche">
    <div class="photo_depute">
	  <?php include_partial('parlementaire/photoParlementaire', array('parlementaire' => $parlementaire, 'height' => 160)); ?>
    </div>
  </div>
  <div class="graph_depute">
    <?php if (!myTools::isEmptyLegislature()) echo include_component('plot', 'parlementaire', array('parlementaire' => $parlementaire, 'options' => array('plot' => 'total', 'questions' => 'true', 'link' => 'true'))); ?>
  </div>
  <div class="barre_activite">
    <?php include_partial('top', array('parlementaire'=>$parlementaire)); ?>
  </div>
  <div class="stopfloat"></div>
</div>

<div class="contenu_depute">
  <?php include_partial('parlementaire/fiche', array('parlementaire'=>$parlementaire, 'commission_permanente' => $commission_permanente, 'missions' => $missions, 'historique' => $parlementaire->getHistorique(true), 'anciens_mandats' => $anciens_mandats, 'main_fonction' => $main_fonction)); ?>
  <?php if (!myTools::isEmptyLegislature()) { ?>
  <div class="bas_depute">
    <h2 class="list_com">Derniers commentaires concernant les travaux de <?php echo $parlementaire->nom; ?> <span class="rss"><a href="<?php echo url_for('@parlementaire_rss_commentaires?slug='.$parlementaire->slug); ?>"><?php echo image_tag('xneth/rss.png', 'alt="Flux rss"'); ?></a></span></h2>
    <?php if ($parlementaire->nb_commentaires == 0) echo '<p>Le travail de '.$parlementaire->getCeCette(false).' n\'a pas encore inspiré de commentaire aux utilisateurs.</p>';
    else {
      echo include_component('commentaire', 'lastObject', array('object' => $parlementaire, 'presentation' => 'noauteur'));
      if ($parlementaire->nb_commentaires > 4)
        echo '<p class="suivant list_com">'.link_to('Voir les '.$parlementaire->nb_commentaires.' commentaires', '@parlementaire_commentaires?slug='.$parlementaire->slug).'</p><div class="stopfloat"></div>'; ?>
    <?php } ?>
  </div>
  <?php } ?>
</div>
