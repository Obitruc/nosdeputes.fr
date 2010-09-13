<?php use_helper('Text') ?>
  <div class="question" id="<?php echo $question->id; ?>">
    <div>
<?php if (!isset($nophoto)) {
  $parlementaire = $question->getParlementaire();
  echo '<h2>'.link_to($parlementaire->nom.'&nbsp;: '.$question->getTitre(), '@question_numero?numero='.$question->numero)."</h2>";
} else echo '<h2>'.link_to($question->getTitre(), '@question_numero?numero='.$question->numero)."</h2>"; ?>
</div>
  <div class="texte_question"><?php
  if (!isset($nophoto))
    echo '<a href="'.url_for('@question_numero?numero='.$question->numero).'" class="intervenant"><img width="50" height="70" alt="'.$parlementaire->nom.'" src="'.url_for('@resized_photo_parlementaire?height=64&slug='.$parlementaire->slug).'" /></a>';
  $inter = preg_replace('/<\/?[a-z]*>|\&[^\;]+\;/i', ' ', $question->getQuestion().' '.$question->getReponse().' Thèmes : '.$question->getThemes());
  $p_inter = '';
  if (isset($highlight)) {
    foreach ($highlight as $h)
      $p_inter .= excerpt_text($inter, $h, 400/count($highlight));
    foreach ($highlight as $h) {
      if (!preg_match('/'.$h.'/', 'strong class="highlight"/'))
        $p_inter = highlight_text($p_inter, $h);
    }
  } else $p_inter = truncate_text(html_entity_decode(strip_tags($inter),ENT_NOQUOTES, "UTF-8"), 400);
  echo $p_inter;
?>
  <div class="contexte">
    <a href="<?php echo url_for('@question_numero?numero='.$question->numero); ?>">Lire la suite de la question</a></div>
  </div>
  </div>
