#!/bin/bash

cd $(dirname $0)
source ../../bin/db-external.inc || source ../../bin/db.inc
source ../../bin/init_pyenv27.sh
ANroot="https://www.assemblee-nationale.fr/"

echo "Downloading Amendements from OpenData AN..."
mkdir -p opendata
rm -f Amendements_*.json* all_amdts_opendataAN.tmp
rm -rf opendata/json
#wget -q https://data.assemblee-nationale.fr/static/openData/repository/LOI/amendements_legis/Amendements_XIV.json.zip -O Amendements_XIV.json.zip
wget -q https://data.assemblee-nationale.fr/static/openData/repository/$LEGISLATURE/loi/amendements_legis/Amendements_XV.json.zip -O Amendements_OD.json.zip
unzip Amendements_OD.json.zip -d opendata > /dev/null
echo "Extracting list of Amendements from OpenData AN..."
touch all_amdts_opendataAN.tmp
ls opendata/json | while read dossier; do ls opendata/json/$dossier | while read texte; do
 find opendata/json/$dossier/$texte/ -type f -name "*.json" |
  while read JSON; do
   URL=`sed -r 's|^.*"numeroLong": "[I\-]*([^" ]+).*"prefixeOrganeExamen": "([^"]+)".*"urlDivisionTexteVise": "/(..)/textes/([^.]+)\.asp.*$|'$ANroot'\3/amendements/\4/\2/\1.asp\n|' $JSON`
   if ! echo $URL | grep $ANroot > /dev/null; then
    ID=`echo $JSON | sed -r 's|^.*/([^/]+)\.json$|\1|'`
    URL=`curl -sIL "https://www.assemblee-nationale.fr/dyn/$LEGISLATURE/amendements/$ID" | grep 'location:' | tail -1 | sed 's/^.*http:/https:/' | sed 's|/dyn/|/|' | sed -r 's/^(.*).$/\1.asp/'`
   fi
   echo $URL
  done
done; done | sort -u >> all_amdts_opendataAN.tmp
rm -f Amendements_*.json*
rm -rf opendata/json

echo "Extracting list of Amendements from search engine AN..."
searchurl="https://www2.assemblee-nationale.fr/recherche/query_amendements?typeDocument=amendement&leg=$LEGISLATURE&idExamen=&idDossierLegislatif=&missionVisee=&numAmend=&idAuteur=&idArticle=&idAlinea=&sort=&dateDebut=&dateFin=&periodeParlementaire=&texteRecherche=&format=html&tri=ordreTexteasc&typeRes=liste&rows="
start=1
total=$(curl -sL "${searchurl}5"|
  grep '"nb_resultats"'         |
  sed 's/^.*:\s*//'             |
  sed 's/,\s*$//')
rm -f all_amdts_searchAN.tmp
while [ $start -lt $total ]; do
  curl -sL "${searchurl}1000&start=$start"      |
    grep '^\['                                  |
    sed 's/","/\n/g'                            |
    sed 's/^.*|http:/https:/'                   |
    sed 's/|.*$//'                              |
    sed 's|\\/|/|g' >> all_amdts_searchAN.tmp
  start=$(($start + 1000))
done

cat all_amdts_opendataAN.tmp all_amdts_searchAN.tmp |
  sed 's#/dyn/#/#'                                  |
  sed -r 's#([0-9])$#\1.asp#'                       |
  sort -u > all_amdts_AN.tmp

echo "Extracting list of Amendements from NosDéputés..."
echo 'SELECT source FROM amendement WHERE sort NOT LIKE "Rect%" ORDER BY source'    |
  mysql $MYSQLID $DBNAME                                                            |
  sed -r 's|(/T?A?[0-9]{4}[A-Z]?/)([0-9]+\.asp)|\1AN/\2|'                           |
  grep -v "cr-cfiab/12-13/c1213068"                                                 |
  grep -v "source"                                                                  |
  sed 's/http:/https:/'						                    |
  sed 's#/dyn/#/#'                                                                  |
  sed -r 's#([0-9])$#\1.asp#'                                                       |
  sort > all_amdts_nosdeputes.tmp

echo "Analysing diff..."
extra=$(diff all_amdts_AN.tmp all_amdts_nosdeputes.tmp | grep "^>" | wc -l)
if [ $extra -gt 0 ]; then
  echo "- NosDéputés has $extra Amendements not in AN's OpenData yet(?):"
  diff all_amdts_AN.tmp all_amdts_nosdeputes.tmp    |
    grep "^>"                                       |
    sed 's/^> //' > extra_amdmts_ND
    echo 'Full list available in "extra_amdmts_ND"'
  echo
fi

ignoring=$(cat missing_amdts_to_ignore.list 2> /dev/null | tr "\n" "|" | sed 's/|$//')
missing=$(diff all_amdts_AN.tmp all_amdts_nosdeputes.tmp | grep "^<" | grep -vP "$ignoring" | wc -l)
if [ $missing -gt 0 ]; then
  echo "There are $missing Amendements missing, reloading them:"
  diff all_amdts_AN.tmp all_amdts_nosdeputes.tmp    |
    grep "^<"                                       |
    grep -vP "$ignoring"                            |
    sed 's/^< //'                                   |
    grep .                                          |
    while read AMurl; do
      AMfile=$(echo "$AMurl" | sed 's|/|_-_|g')
      perl download_one.pl "$AMurl" 2>/dev/null && python parse_amendement.py "html/$AMfile" > "json/$AMfile" || echo "ERROR: $AMurl missing from AN web"
      if grep '"sort": "", "auteurs": "", "parent": "", "serie": "", "expose": "", .*"date": "1970-01-01", "auteur_reel": "", "sujet": "", "texte": ""}' "json/$AMfile" > /dev/null; then
        echo "ERROR: $AMurl missing from AN web"
        rm -f "json/$AMfile"
      fi
    done
  AMdone=$(ls json | wc -l)
  echo
  echo "$(($missing - $AMdone)) missing amendements from AN's OpenData could not be found on AN's website"
  echo 'All '"$AMdone"' missing found Amendements reloaded and parsed into batch/amendements/json'
fi

rm -f all_amdts_*.tmp
