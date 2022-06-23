#!/usr/bin/perl

use HTML::TokeParser;
use HTML::Entities;
use Encode;
require "./finmandats.pm";
require "../common/common.pm";

$file = shift;
$yml = shift || 0;
$display_text = shift;

my %bureau;

open(FILE, "bureau.csv");
@bureaulines = <FILE>;
$bureaulines = "@bureaulines";
close FILE;

foreach $line (split /\n/, $bureaulines) {
  @vals = split /;/, $line;
  $bureau{trim($vals[0])} = lc(trim($vals[1]));
}

open(FILE, $file);
@string = <FILE>;
$string = "@string";
close FILE;
$string =~ s/\r//g;
$string =~ s/ / /ig;
$string =~ s/\&nbsp;?/ /ig;
$string =~ s/Univerist/Universit/g;
$string =~ s/aglommération/agglomération/g;
$string =~ s/[\n\s]+/ /g;
$string =~ s/^.*(<h1 class="deputy-headline-title)/\1/i;
$string =~ s/<div id="actualite".*<\/div>(<div id="fonctions")/\1/i;
$string =~ s/<div id="travaux".*$//i;
while ($string =~ s/(<li class="contact-adresse">([^<]*)?)(<\/?p>)+(.*<\/li>(<li class="contact-adresse">|<\/ul>))/\1 \4/gi) {}
$string =~ s/(<(div|p|ul|\/li|abbr|img|dt|dd|h\d)[ >])/\n\1/ig;
$string =~ s/<\/?sup>//ig;
$string =~ s/<svg[^>]*>.*?<\/svg>//ig;
$string =~ s/’/'/g;
$string =~ s/\s*'\s*/'/g;

if ($display_text) {
  print $string;
  exit;
}

my %depute;
my %groupes;
my %orgas;
my %mission;

sub clean_vars {
  $encours = $lieu = $organisme = $fonction = "";
  $mission = 0;
  $missioninfo = 0;
}

my %premiers_mandats;
sub add_mandat {
  $start = shift;
  $end = shift;
  $cause = shift;
  if ($cause =~ /(remplacement.*)\s*:\s*(.*)\s*$/i && $cause !~ /lection/i && !$depute{'suppleant'}) {
    $depute{'suppleant_de'} = $2;
    $cause =~ s/\s*:\s*(.*)\s*$/ \(\1\)/;
  }
  $cause =~ s/^É/é/;
  $cause =~ s/(du gouvernement) :.*$/\1/i;
  $premiers_mandats{"$start / $end / ".lc($cause)} = 1;
  $depute{'debut_mandat'} = max_date($start,$depute{'debut_mandat'});
  $depute{'fin_mandat'} = max_date($end,$depute{'fin_mandat'}) if ($end !~ /^$/ && ($start == $end || max_date($end,$depute{'debut_mandat'}) != $depute{'debut_mandat'} ));
}

if ($file =~ /(\d+)/) {
  $depute{'id_institution'} = $1;
  $depute{'url_institution'} = "http://www2.assemblee-nationale.fr/deputes/fiche/OMC_PA$1";
  $depute{'old_url_institution'} = "http://www.assemblee-nationale.fr/$legislature/tribun/fiches_id/$1.asp";
  $depute{'photo'} = "http://www2.assemblee-nationale.fr/static/tribun/$legislature/photos/$1.jpg";
  $depute{'old_photo'} = "http://www.assemblee-nationale.fr/$legislature/tribun/photos/$1.jpg";
}

$read = "";
$parti = "";
$address = "";
$done = 0;
foreach $line (split /\n/, $string) {
  #print STDERR "$line\n";
  $line =~ s/<\/?sup>//g;
  if ($line =~ /<h1>(.+)<\/h1>/i) {
    $depute{'nom'} = $1;
    $depute{'nom'} =~ s/,.*$//;
    $depute{'nom'} =~ s/[\- ]*Président.*$//;
    $depute{'nom'} =~ s/^(M[.mle]+) //;
    if ($1 =~ /e/) {
      $depute{'sexe'} = "F";
    } else {
      $depute{'sexe'} = "H";
    }
  } elsif (!$depute{'circonscription'} && $line =~ /(<ul> <li>|"deputy-head?line-sub-title">)([^<]*) \((\d+[èrme]+) circonscription/i) {
    $depute{'circonscription'} = "$2 ($3)";
  } elsif ($line =~ /Née? le ([0-9]+e?r? \S+ [0-9]+)( [àaux]+ (.*))?/i) {
    $depute{'date_naissance'} = join '/', reverse datize($1);
    $lieu = $3;
    $lieu =~ s/\s*\(\)\s*//g;
    $lieu = trim($lieu);
    $depute{'lieu_naissance'} = $lieu if ($lieu !~ /^$/);
    $read = "profession";
  } elsif ($line =~ /<li class="allpadding">\s*([^<]*)\s*/i) {
    $depute{'collabs'}{$1} = 1;
  } elsif ($line =~ /<dt>Suppléant<\/dt>/i) {
    $read = "suppleant";
  } elsif ($line =~ /<dt>Rattachement au titre du financement/i) {
    $read = "parti";
  } elsif ($line =~ /<dl class="adr">/i) {
    $read = "adresse";
    $address = "";
  } elsif ($line =~ /<dd class="tel">.*<span class="value">([^<]*)</i) {
    delete $depute{'adresses'}{$address};
    $line =~ s/<[^>]+>//g;
    $address .= " ".trim($line);
    $depute{'adresses'}{$address} = 1;
  } elsif ($read !~ /^$/) {
    if ($read =~ /adresse/) {
      if ($line =~ /<dd/i) {
        $address .= $line;
        $address =~ s/<[^>]+>//g;
        if ($line =~ /<\/dl>/i) {
          $address = trim($address);
          $depute{'adresses'}{$address} = 1;
          $read = "";
        }
      }
    } elsif ($line =~ /<dt>/i) {
      $read = "";
    } else {
      $line =~ s/<[^>]+>//g;
      $line = trim($line);
      $depute{"$read"} = $line if ($line !~ /^$/ && !($line =~ /^Actualité/ && $read =~ /profession/));
      if ($read =~ /suppleant/) {
        $depute{"$read"} =~ s/[(,\s]+décédé.*$//i;
        $depute{"$read"} =~ s/Mlle /Mme /;
        $depute{"$read"} =~ s/([A-ZÀÉÈÊËÎÏÔÙÛÜÇ])(\w+ ?)/\1\L\2/g;
      }
      $read = "" if ($line !~ /^$/);
    }
  } elsif ($line =~ /composition du groupe"[^>]*>([^<]+)</i) {
    $groupe = lc($1);
    $groupe =~ s/É/é/g;
    $groupe =~ s/^union pour la démocratie française$/union des démocrates et indépendants/;
    $groupe =~ s/^rassemblement pour la république$/union pour un mouvement populaire/;
    $groupe =~ s/^socialiste$/socialiste, républicain et citoyen/;
    $groupe =~ s/^non inscrit$/Députés non inscrits/;
    if ($line =~ /(apparentée?|présidente?)( du groupe)? /i) {
      $gpe = $groupe." / ".(lc $1);
    } else {
      $gpe = $groupe." / membre";
    }
    $gpe .= "e" if ($depute{'sexe'} eq "F" && $gpe =~ /(président|apparenté)$/);
    $depute{'groupe'}{$gpe} = 1;
  } elsif ($line =~ /mailto:([^'"]+@[^'" ]+)['" ]/i) {
    $depute{'mails'}{$1} = 1;
  } elsif ($line =~ /<a [^>]*class="(url|facebook|twitter topmargin)" *href=['"]\s*([^"']+)\s*['"]/i) {
    $site = $2;
    if ($1 =~ /twitter/) {
      $site =~ s/\/$//;
    }
#    $site =~ s#^(http://| )*#http://#i; #Bug plus d'actualité ?
    $site =~ s#(facebook\.com/)www\.facebook\.com/#\1#i;
    $site =~ s#\s+(/)?$#\1#;
    $site =~ s#/+$#/#g;
    if ($site =~ s/^\s*(https?:\/\/)?([^\/]+@[^\/]+)$/\2/) { #Les url twitter sont indiquées avec un @
      $depute{'mails'}{$site} = 1;
    } else {
      if ($site !~ /facebook\.com\/(sharer\.php|sandramarsaudlarepubliquenmarche|BSmedoc|colas\.roy\.2017)/) { #Evite de prendre les boutons de partage de l'AN et les comptes désuets
        $site =~ s/(twitter.com\/)[\s@]+/\1/i;
        $site =~ s/(twitter.com\/.*)\?.*$/\1/i;
        # remove bad twitter accounts from AN
        if ($site !~ /twitter.com\/(valeriebeauvais2017|sttrompille|Darrieussecq|bernarddeflesselles|Marc_Delatte|davidlorion|Josso2017|ColasRoy2017|GCHICHE2017|obono2017|celiadeputee2017|Vincent.Ledoux59|EricDiardDepute|MireilleRobert|Fdumas2017|PascalBois2017|pgoulet58|micheldelpon|DipompeoChris|Valeria_Faure_M|Thourot2017|FabienGoutte|ainakuric2017|FJolivet2017|CaroleBB2017|ludomds|blanchet2017|MaudPetit_LREM|en_marche_77|BPeyrol_REM0303|CFABRE2017|soniakrimi50|NLePeih2017|Haury2017|CRoussel_06|c_vignon3103|philippemichel1|pierrecabare|zivkapark2017|Laudubray|ykerlogot|RaphaelGauvain|DDavid2017|iflorennesLREM|riottonenmarche|jcleclabart2017|PascalBoisLREM|BCouillard2017|mtamverhaeghe|fbachel1er|jenniferdt5915|caroleem54|Sach_He|DidierParis|g_vuilletet|TuffnellLREM17|trastour2017|roserenxavier|c_naegelen|b_poirson|jlthieriot|HuguetteLREM46|cjerretie|Fabien_Rssl|sonjoachim)/i) {
          $depute{'sites_web'}{$site} = 1;
        }
      }
    }
  } elsif ($line =~ /id="hemicycle-container" data-place="(\d+)">/i) {
    $depute{'place_hemicycle'} = $1;
  } elsif ($line =~ /\(Date de début de mandat[\s:]+([\d\/]+)( \((.*)\)\))?/i) {
    add_mandat($1,"",$3);
  } elsif ($line =~ /Mandat du ([\d\/]+)([ <!\-]+\(.*\))?[ >!\-]+au ([\d\/]+)( \((.*)\))?/i) {
    add_mandat($1,$3,$5);
#  } elsif ($line =~ /(Reprise de l'exercice.*député.*) le[ :]+([\d\/]+)/) {
#    add_mandat($2, "", $1);
  } elsif ($line =~ /Anciens mandats et fonctions à l'Assemblée nationale/) {
     $done = 1;
     $encours = "";
  } elsif ($line =~ /<!--fin.*tab.*-->/) {
     $encours = "";
  } elsif ($line =~ /^<h4 class/ && !$done) {
    clean_vars();
    $line =~ s/\s*<[^>]+>\s*/ /g;
    $line =~ s/[  \s]+/ /g;
    $line = trim($line);
    if ($line =~ /(Bureau|Commissions?|Missions? (temporaire|d'information|auprès du Gouvernement)s?|Délégations? et Offices?|Groupes de travail)/) {
      $encours = "fonctions";
      if ($line =~ /Missions? (auprès|temporaires)?/) {
        $mission = 1;
        if ($1) {
          $encours = "extras";
        }
      } elsif ($line =~ /information/) {
        $missioninfo = 1;
      }
    } elsif ($line =~ /(Organismes? extra-parlementaires?|Fonctions? dans les instances internationales ou judiciaires)/) {
      $encours = "extras";
    } elsif ($line =~ /Mandats? (loca[lux]+ en cours|intercommuna)/i) {
      $encours = "autresmandats";
    } elsif ($line =~ /Groupes? d'(études?|amitié)/i) {
      $encours = "groupes";
      $type_groupe = $line;
    }
  } elsif ($encours !~ /^$/ && $line !~ /^<h[23]/i) {
    #print STDERR "TEST $encours: $line\n";
    $oline = $line;
    $line =~ s/\s*<[^>]+>\s*/ /g;
    $line =~ s/([^à]+)[  \s]+/\1 /g;
    $line = trim($line);
    next if ($line =~ /^$/);
    if ($oline =~ /<span class="dt">/i) {
      $line =~ s/^\(((Président|Rapporteur)(e)?( (général|spécial))?).*\)$/\1\3/;
      $line =~ s/Rapporteur(e)? sur .*$/rapporteur\1 thématique/i;
      $line =~ s/\([^)]*\)//i;
      $line =~ s/délégue/délégué/i;
      $line =~ s/ par le Président de l'Assemblée nationale\s*//i;
      $fonction = lc $line;
      next;
    } elsif ($encours =~ /anciensmandats/) {
      if ($line =~ /du (\d+\/\d+\/\d+) au (\d+\/\d+\/\d+) \((.*)\)/i) {
        $dates = "$1 / $2";
        $fonction = $3;
        $tmporga = lc($organisme);
        $tmporga =~ s/\W/./g;
        $fonction =~ s/\s*(d[elau'\s]+)?$tmporga\s*//i;
        if (!$orgas{trim($lieu)." / ".trim($organisme)}) {
          $depute{$encours}{trim($lieu)." / ".trim($organisme)." / ".trim($fonction)." / $dates"} = 1;
          $orgas{trim($lieu)." / ".trim($organisme)} = 1;
        }
      } elsif ($line =~ /^\s*(.[^A-Z\(]+) d(e la |[ue]s? |'|e l')([A-ZÀÉÈÊËÎÏÔÙÛÇ].*)$/) {
#      } elsif ($line =~ /^\s*(.[^(A-ZÀÉÈÊËÎÏÔÙÛÇ]*) d([ue](s| la)? |'|e l')(\U.*)$/) {
        $organisme = $1;
        $lieu = $3;
        $organisme = "Conseil de Paris" if ($lieu =~ s/ \(Département de Paris\)/ (Département)/);
      } else {
        $line =~ s/Communauté Agglomération/Communauté d'agglomération/i;
        $lieu = $line;
        if ($line =~ /^\s*c(ommunauté d?[elau'\s]*\S+) (d[elasu'\s]+)?(\U.*)$/i) {
          $organisme = "C$1";
          $lieu = $3;
        } else {
          $organisme = "Communauté d'agglomération";
        }
      }
    } elsif ($encours =~ /autresmandats/) {
      if ($line =~ /^\s*(.*?) (de la )?c(ommunauté (urbaine|d[elaus'\s]+\S+)) (d[elsau\s]*?['\s])?(\U.*)$/i) {
        $fonction = lc $1;
        $organisme = "C".(lc $3);
        $lieu = $6;
        $organisme =~ s/[cC](ommunauté d)(e (communes? de )?l)?'[aA](gglomération)s?/C\1'a\4/;
        $organisme =~ s/(Communauté de commune)$/\1s/;
      } else {
        $lieu = "";
        $line =~ s/(Con(seil|grès)|Gouvernement)/\L\1/;
        if ($line =~ s/^([^(]*?) d([ue](s| la)? |'|e l')([A-ZÀÉÈÊËÎÏÔÙÛÇ].*)$/\1/) {
          $lieu = $4;
        } elsif ($line =~ s/^(.*)\(([A-ZÀÉÈÊËÎÏÔÙÛÇ].*)\)$/\1/) {
          $lieu = $2;
        }
        $lieu =~ s/(Paris|Lyon|Marseille) \(?(\d+[erèm]+ (Arrondissement|secteur))\)?.*$/\1 \2/i;
        $line =~ s/\s+$//;
        $organisme = ucfirst($4) if ($line =~ s/^(.*) d((u|e la) |e l')(.*)$/\1/);
        $fonction = lc $line;
        $fonction =~ s/ du$//;
        if ($fonction =~ /maire/i || $fonction =~ s/^(conseillere? )municipal (déléguée?)/\1\2/) {
          $organisme = "Conseil municipal";
        }
      }
      $lieu =~ s/, (.*)$/ (\1)/;
      $hashstr = trim(ucfirst($lieu))." / ".trim($organisme);
      if (!$orgas{$hashstr}) {
        $depute{$encours}{$hashstr." / ".trim($fonction)} = 1;
        $orgas{$hashstr} = 1;
      }
    } elsif ($encours =~ /groupes/) {
      $line =~ s/Groupe d'études //;
      $type = "Groupe d'amitié ";
      $type = "Groupe d'études " if ($type_groupe =~ /étude/i);
      $line =~ s/\(République du\)/(République démocratique du)/i;
      if (!$groupes{$line}) {
        $groupes{$line} = 1;
        if (!$orgas{$type.trim($line)}) {
          $depute{$encours}{$type.trim($line)." / ".lc(trim($fonction))} = 1;
          $orgas{$type.trim($line)} = 1;
        }
      }
    } else {
      if ($mission && $line =~ /^(.*?)\(?\s*((Premi(e|è)re? ministre|Ministère|Secrétariat)[^)]*)\)?\s*$/) {
        $organisme = trim($1);
        $minist = trim($2);
        if ($organisme !~ /^$/) {
          if ($minist =~ / - (Premi(e|è)re? min|Minist|Secr).*$/) {
            $minist = "Gouvernement";
          } else {
            $minist =~ s/[^a-zàéèêëîïôù]+$//;
          }
          $minist = "Mission temporaire pour le $minist";
          $organisme =~ s/^La proposition /Proposition /;
          $organisme = "$minist : $organisme";
          $fonction = "chargé".($depute{'sexe'} eq "F" ? "e" : "")." de mission";
        } else {
          $organisme = "Gouvernement";
          $fonction = $minist;
          $fonction =~ s/^Ministère/Ministre/;
          $fonction =~ s/^Secrétariat d'/Secrétaire d'/;
        }
      } elsif ($line =~ s/ de l'Assemblée nationale depuis le : \d.*$//) {
        $organisme = "Bureau de l'Assemblée nationale";
        $fonction = lc $line;
        if ($fonction =~ /questeur/i) {
          $depute{"fonctions"}{"Questure / ".trim($fonction)} = 1;
          $orgas{"questure"} = 1;
        }
      } else {
        $organisme = ucfirst($line);
        $organisme =~ s/("|\(\s*|\s*\))//g;
        if ($missioninfo && $organisme !~ /^Mission/) {
          $organisme = "Mission d'information $organisme";
        }
        $organisme =~ s/( et de contrôle)\s*$/\1 de la commission des finances/;
      }
      if (!$orgas{trim($organisme)}) {
        $depute{$encours}{trim($organisme)." / ".trim($fonction)} = 1;
        $orgas{trim($organisme)} = 1;
      }
    }
  }
}

if ($bureau{$depute{"id_institution"}}) {
  $fonction = $bureau{$depute{"id_institution"}};
  $fonction =~ s/s? de l'assemblée nationale//g;
  if ($fonction !~ /e$/ && $depute{"sexe"} eq "F") {
    $fonction .= "e";
  }
  $depute{"fonctions"}{"Bureau de l'Assemblée nationale / ".$fonction} = 1;
  $orgas{"Bureau de l'Assemblée nationale"} = 1;
  if ($fonction =~ /questeur/i) {
    $depute{"fonctions"}{"Questure / ".$fonction} = 1;
    $orgas{"questure"} = 1;
  }
}

#On récupère le nom de famille à partir des emails
$nomdep = $depute{'nom'};
@noms = split / /, $nomdep;
if ($nomdep =~ /Alexandra Valetta Ardisson/) {
  $depute{'nom_de_famille'} = 'Valetta Ardisson';
} elsif ($nomdep =~ /Florence Lasserre/) {
  $depute{'nom_de_famille'} = 'Lasserre';
} elsif ((join " ", keys %{$depute{'mails'}}) =~ /(\S+)\@assemblee/) {
  $login = $1;
  $login =~ s/^[^\.]+\.//;
  for($i = 0 ; $i <= $#noms ; $i++) {
    next if ($noms[$i] =~ /^[ld]e/i);
    $tmpnom = lc($noms[$i]);
    $tmpnom =~ s/[àÀéÉèÈêÊëËîÎïÏôÔùÙûÛçÇ]/./ig;
    $tmpnom =~ s/\.+/.+/g;
    if ($login =~ /$tmpnom/i) {
      if ($nomdep =~ /.\s([l][ea]s?\s)?(\S*?$tmpnom.*$)/i) {
        $depute{'nom_de_famille'} = $1.$2;
        last;
      }
    }
  }
}
#Si pas de nom de famille, on le récupère par le nom
if (!$depute{'nom_de_famille'}) {
  if ($depute{'nom'} =~ /\S (des? )?(.*)$/i) {
    $depute{'nom_de_famille'} = $2;
  }
}
$depute{'nom_de_famille'} = trim($depute{'nom_de_famille'});

#clean doublons mandats
my %tmp_mandats;
foreach $m (keys %premiers_mandats) {
  $date1 = $m;
  $date1 =~ s/ \/ .*$//;
  if (!$tmp_mandats{$date1} || $tmp_mandats{$date1} =~ / \/ +\/ /) {
    $tmp_mandats{$date1} = $m;
  }
}
foreach $m (values %tmp_mandats) {
  $depute{'premiers_mandats'}{$m} = 1;
}
if ($depute{"parti"}) {
  if ($depute{"parti"} =~ s/Non rattaché\(s\)/Non rattaché/i && $depute{"sexe"} eq "F") {
    $depute{"parti"} .= "e";
  };
  $depute{"parti"} =~ s/ \(Debout la République\)//i;
}

if ($yml) {
  print "  depute_".$depute{'id_institution'}.":\n";
  foreach $k (keys %depute) {
    if (ref($depute{$k}) =~ /HASH/) {
      print "    ".lc($k).":\n";
      foreach $i (keys %{$depute{$k}}) {
        print "      - $i\n";
      }
    } else {
      print "    ".lc($k).": ".$depute{$k}."\n";
    }
  }
  print "    type: depute\n";
  exit;
}

print "{";
foreach $k (keys %depute) {
  if (ref($depute{$k}) =~ /HASH/) {
    print '"'.lc($k).'": ['.join(", ", map { s/"/\\"/g; '"'.$_.'"' } keys %{$depute{$k}})."], ";
  } else {
    $depute{$k} =~ s/"/\\"/g;
    print '"'.lc($k).'": "'.$depute{$k}.'", ';
  }
}
print '"type": "depute"}'."\n";
