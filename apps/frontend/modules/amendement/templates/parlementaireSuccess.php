<?php
$titre = "Amendements";
echo include_component('parlementaire', 'header', array('parlementaire' => $parlementaire, 'titre' => $titre));
?>
<div class="amendements">
<?php  echo include_component('amendement', 'pagerAmendements', array('amendement_query' => $amendements)); ?>
</div>