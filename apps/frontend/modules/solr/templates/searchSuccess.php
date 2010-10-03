<?php 
$sf_response->setTitle("Recherche de $query");  $style = "xneth";

function url_search($query, $args) 
{
  $extra = '';
  $url = "solr/search?query=".$query;
  foreach($args as $key => $value) {
    if (is_array($value)) {
      if (count($value)) { $extra .= '&'.$key.'='.implode(',', array_keys($value)); }
    }    
    else { $extra .= '&'.$key.'='.$value; }
  }
  return $url.$extra;
}

function link_search($text, $query, $args, $options) 
{
  if($options) { return link_to($text, url_search($query, $args), $options); }
  else { return link_to($text, url_search($query, $args)); }
}

function addToDate($date, $interval) {
  $date = preg_replace('/\-/', '', $date);
  $annee = substr($date, 0, 4);
  $mois = substr($date, 4, 2);
  $jour = substr($date, 6, 2);
  if($interval == 'mois') {
    $date = mktime(0, 0, 0, $mois + 1, $jour, $annee); 
    #int mktime  ("H","m","s","M","j","Y", -1)
  }
  else { $date = mktime(0, 0, 0, $mois, $jour - 1, $annee); }
  $date = date("Ymd", $date);
  return $date;
}

$recherche = preg_replace('/"/', '&quot;', $query);

switch ($vue) {
  case "jour":
    $periode_text = 'le '.myTools::displayShortDate($start);
    $intitule_resultats = 'Résultats pour "<em>'.$recherche.'</em>" '.$periode_text;
    $graph = 0;
    break;
  case "mois":
    $periode_text = 'en '.myTools::displayMoisAnnee($start);
    $intitule_resultats = 'Résultats pour "<em>'.$recherche.'</em>" ';
    $graph = 1;
    break;
  case "par_jour":
    $periode_text = 'entre le '.myTools::displayShortDate($start).' et le '.myTools::displayShortDate($end);
    $intitule_resultats = 'Résultats pour "<em>'.$recherche.'</em>" ';
    $graph = 1;
    break;
  case "par_mois":
    $periode_text = 'entre '.myTools::displayMoisAnnee($start).' et '.myTools::displayMoisAnnee($end);
    $intitule_resultats = 'Résultats pour "<em>'.$recherche.'</em>" ';
    $graph = 1;
    break;
  default:
    $periode_text = "supprimer les critère de dates";
    $intitule_resultats = 'Recherche de "<em>'.$recherche.'</em>';
    $graph = 1;
}

//////////////////// DEBUT SANS AJAX /////////////////////
//////////////////////////////////////////////////////////

if (!$ajax) :
?>
<script type="text/javascript">
<!--
function urlParams(params) {
	param = new Object();
	if(jQuery.isArray(params)) {
    jQuery.each(params, function() {
      val = this.split('=');
      param[val[0]] = val[1];
    });
  }
  else {
    val = params.split('=');
    param[val[0]] = val[1];
  }
	return param;
}

periode = new Array();
date_href = new Array();
nb_li = 0;
bh = 0;
nh = 0;
if(location.search.substring(1)) { parametre = urlParams(location.search.substring(1).split('&')); }
else { parametre = new Object(); }

timer4update = null;
function realAjaxUpdate(lien) {
  lien += '&ajax=1';
  $('#results_container').load(lien, function() {$('#results_container').fadeTo(100,1);});
}
function ajaxUpdateFor(lien) {
  if (timer4update) {
    clearTimeout(timer4update);
    timer4update = null;
  }
  $('#results_container').css('opacity', 0.5);
  timer4update = setTimeout('realAjaxUpdate("'+lien+'")', 2500);
}

function constructLien(date) {
  lien = document.location+'';
  lien = lien.replace(/\??date=[^&]+/, '');
  lien = lien.replace(/#.*/, '');
  if (!lien.match(/\?/))
    lien += '?';
  else
    lien += '&';
  lien += 'date='+date;
  return lien;
}

$(document).ready(function() {
  $(".date li").each(function() {
    if($(this).height() > bh) { bh = $(this).height(); }
    date_li = $(this).attr("title").split('--');
    date_href[nb_li] = $(this).find('a').attr("href");
    $(this).find(".hover_graph").attr("onclick", "document.location.replace('http://"+location.host+date_href[nb_li]+"')");
    $(this).find(".hover_graph").css("cursor", "pointer");
    periode[nb_li] = date_li[0];
    nb_li++;
  });
  if(bh <= 30) {
    $(".date li").each(function() {
      nh = $(this).height() * 2; $(this).height(nh);
    });
    bh = bh * 2;
  }
  if(bh <= 170) { bh = bh + 30; $(".date").height(bh); }
  $(".date").fadeIn(300); /* à revoir */
	$(function() {
		$("#slider_date_graph").slider({
			range: true,
			min: 0,
			max: nb_li-1,
			values: [0, nb_li],
			slide: function(event, ui) {
			  from = date_href[ui.values[0]].split('?');
			  from = urlParams(from[1].split('&'));
			  from = from["date"].split('%2C');
			  to = date_href[ui.values[1]].split('?');
			  to = urlParams(to[1].split('&'));
			  to = to["date"].split('%2C');
			  parametre["date"] = from[0]+'%2C'+to[0];

			  lien = constructLien(parametre['date']);
			  document.location = '#date='+parametre['date'];

			  if(ui.values[0] == ui.values[1]) { 
			    texte_periode = '<a href="'+lien+'" style="text-decoration: underline;"><strong>'+periode[ui.values[0]]+'<\/strong><\/a>';
			  }
			  else { 
			    texte_periode = '<a href="'+lien+'" style="text-decoration: underline;"><strong>entre '+periode[ui.values[0]]+' et '+ periode[ui.values[1]]+'<\/strong><\/a>';
			  }
			  ajaxUpdateFor(lien);
			  $("#periode").text("");
				$("#periode").append(texte_periode);
			}
		});
		$("#periode").text('entre ' + periode[$("#slider_date_graph").slider("values", 0)] + ' et ' + periode[$("#slider_date_graph").slider("values", 1)]);
	});
});
//-->
</script>

<div class="solr">
  <div class="searchbox">
  <form action="">
  <p>
    <input name="search" value="<?php echo $recherche; ?>" />
    <input type="submit" value="Rechercher"/>
  </p>
  </form>
</div>
<h1><?php echo $intitule_resultats; ?></h1>
<?php 
if($graph) { 
  $width_date = 900;
  $left = 2;
  $espacement = 4;
  $width = (($width_date - $left) / count($fdates['values'])) - $espacement;
?>
<div class="cont_date_graph">
   <span>Affiner par date :</span> <span id="periode"><?php echo $periode_text; ?></span>
  <div class="date" style="width: <?php echo $width_date ?>px;">
  <ul>
    <?php $i = 0; foreach($fdates['values'] as $date => $nb) :
    $i++;
    $height = round($nb['pc']*100/($fdates['max']) * 2);
    $padding = 200-$height; 
    $left = $left + $width; if($i < (count($fdates['values']))) { $left = $left + $espacement; }
    $newargs = $selected;
    
    $newargs['date'] = $date.'%2C'.$date;
    
    if(($vue == 'jour') or ($vue == 'par_jour') or ($vue == 'mois')){ 
      $title_date = myTools::displayShortDate($date).' -- '.$nb['nb'].' résultats';
    }
    if($vue == 'par_mois') { 
      $title_date = ucfirst(myTools::displayMoisAnnee($date)).' -- '.$nb['nb'].' résultats';
    }
    if($vue == 'par_mois') { 
      $newargs['date'] = $date.'%2C'.addToDate($date, 'mois');
    }
    
    echo '<li title="'.$title_date.'" class="jstitle" style="list-style-image: none; width: '.$width.'px; height: '.$height.'px; left: '.$left.'px;">'; 
    echo '<div class="hover_graph" title="'.$title_date.'" style="width: '.$width.'px; height: '.$padding.'px;	bottom: '.$height.'px;"></div><span class="text_graph">'.link_search($nb['nb'], $query, $newargs, array()).'</span>'; 
    
    # echo ' '.$nb['nb'].' résultats ('; printf('%02d', $nb['pc']*100/($fdates['max'])); echo '%)';
    ?>
    </li>
    <?php endforeach; ?>
  </ul>
  </div>
  <div id="slider_date_graph"></div>
</div>
<?php } ?>
<div id="results_container">
<?php 
endif; 
///////////////////// FIN SANS AJAX /////////////////////
?>
<div class="nb_results">
  <h2>Résultats <?php echo $results['start']+1; ?> à <?php echo min($results['end'],$results['numFound']); ?> sur <?php echo $results['numFound']; ?> <strong>triés par <?php echo $sort_type; ?></strong> - 
  <span class="tri">
  <?php 
  $newargs = $selected;
  if ($sort) {
    if (isset($newargs['sort'])) { $newargs['sort'] = 0; }
    echo link_search('trier par pertinence', $query, $newargs, 0); 
  } 
  else {
    $newargs['sort'] = 1;
    echo link_search('trier par date', $query, $newargs, 0); 
  }
  ?>
  </span></h2>
</div>
<div class="options">
  <div class="mail">
  <h3>Alerte email</h3>
  <?php 
  $args = '';
  foreach(array_keys($selected) as $k) {
    if (!is_array($selected[$k])) 
      continue;
    if ($args)
      $args .= '&';
    $args.= "$k=".implode(',', array_keys($selected[$k]));
  }
  echo link_to('Recevoir un email lorsque de nouveaux résultats seront publiés pour cette recherche', 'alerte/create?filter='.urlencode($args).'&query='.urlencode($query));

global $facetName2HumanName;
$facetName2HumanName = array(
			     'Parlementaire' => 'Filtrer par Parlementaire',
			     'Types' => 'Filtrer par type de document',
			     'Tags' => 'Filtrer par tags',
			     'TexteLoi' => 'Document parlementaires',
			     'QuestionEcrite' => 'Questions ecrites',
			     'Tags' => 'Filtrer par tags'
);
function facet2Human($id) {
  global $facetName2HumanName;
  if (!isset($facetName2HumanName[$id])) {
    return $id;
  }
  return $facetName2HumanName[$id];
}
  ?>
  </div>
  <div class="facets">
  <h3>Affiner la recherche</h3>
  <?php 
  if(isset($newargs['date'])) {
    $args_sans_date = $newargs;
    unset($args_sans_date['date']);
    echo '<p><strong>Filtrer par dates</strong></p><ul><li class="selected">'.link_search($periode_text, $query, $args_sans_date, 0).'</li></ul>';
  }
  
  foreach(array_keys($facet) as $k) { if (isset($facet[$k]['values']) && count($facet[$k]['values'])) : ?>
    <div class="<?php echo $k; ?>">
       <p><strong><?php echo facet2Human($facet[$k]['name']); ?></strong></p>
    <ul>
    <?php foreach($facet[$k]['values'] as $value => $nb) : if ($nb) :
      $is_selected = isset($selected[$facet[$k]['facet_field']][$facet[$k]['prefix'].$value]) && 
		  $selected[$facet[$k]['facet_field']][$facet[$k]['prefix'].$value];
    ?>
      <li<?php if ($is_selected) echo ' class="selected"'; ?>><?php 
      $newargs = $selected;
      if ($is_selected) 
        unset($newargs[$facet[$k]['facet_field']][$facet[$k]['prefix'].$value]);
      else			      
        $newargs[$facet[$k]['facet_field']][$facet[$k]['prefix'].$value] = 1;
      echo link_search(facet2Human($value), $query, $newargs, 0); ?>&nbsp;(<?php echo $nb; ?>)
      </li>
    <?php endif; endforeach; ?>
    </ul>
    </div>
  <?php endif; } ?>
  </div>
</div>
<div class="results">
  <?php foreach ($results['docs'] as $record) : ?>
  <div class="item">
  <h4><a href="<?php echo $record['link']; ?>"><?php echo $record['titre']; ?></a></h4>
  <?php if ($record['photo']) { ?>
  <p class="photo"><a href="<?php echo $record['link']; ?>" rel="nofollow"><?php echo $record['photo']; ?></a></p>
  <?php } ?>
  <p class="intervenant"><a href="<?php echo $record['link']; ?>" rel="nofollow"><?php echo $record['personne']; ?></a></p>
  <p class="content"><?php echo $record['highlighting']; ?></p>
  <p class="more"><a href="<?php echo $record['link']; ?>">Consulter</a></p>
  </div>
  <div class="record">
  </div>
  <?php endforeach; ?>
</div>
<div class="pager">
  <div class="next">
  <?php
  if ($results['end']-1 != $results['numFound']) {
    $newargs = $selected;
    $newargs['page'][$results['page'] + 1] = 1;
    echo link_search('page suivante', $query, $newargs, 0); 
  }
  ?>
  </div>
  <div class="last">
  <?php
  $newargs = $selected;
  $newargs['page'][$results['page'] - 1] = 1;
  if ($results['page'] > 1) {
    if (isset($newargs['page'][1]))
    unset($newargs['page'][1]);
    echo link_search('page précédente', $query, $newargs, 0); 
  }
  ?>
  </div>
</div>
</div>
<?php if (!$ajax) : ?>
</div>
<?php endif; ?>
