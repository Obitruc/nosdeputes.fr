#!/usr/bin/perl
use utf8;
use URI;
use WWW::Mechanize;
use HTML::TokeParser;
$legislature = shift || 14;
$verbose = shift || 0;

sub download_fiche {
  $uri = $file = shift;
  $uri =~ s/^\//http:\/\/www2.assemblee-nationale.fr\//;
  print "$uri" if ($verbose);
  $a->max_redirect(0);
  eval { $a->get($uri."?force"); };
  if( not $a->res->is_success and not $a->res->is_redirect ){
    sleep 1;
    eval { $a->get($uri); };
    if( not $a->res->is_success and not $a->res->is_redirect ){
      print STDERR "ERREUR geting $uri twice in a row, skipping it\n";
      return;
    }
    print STDERR "\n";
  }
  $status = $a->status();
  if (($status >= 300) && ($status < 400)) {
    $location = $a->response()->header('Location');
    if (defined $location) {
      print "...redirected to $location..." if ($verbose);
      $a->get(URI->new_abs($location, $a->base()));
    }
    $file = $location;
  }
  $file =~ s/^.*\/([^\/]+)/$1/;
  $file =~ s/\?force//;
  mkdir html unless -e "html/" ;
  open FILE, ">:utf8", "html/$file" || warn("cannot write on html/$file");
  print FILE $a->content;
  close FILE;
  print "\nhtml/$file written\n" if ($verbose);
  return $file;
}
$a = WWW::Mechanize->new();
print "https://www2.assemblee-nationale.fr/deputes/liste/alphabetique\n" if ($verbose);
$a->get("https://www2.assemblee-nationale.fr/deputes/liste/alphabetique");
$content = $a->content;
$p = HTML::TokeParser->new(\$content);
while ($t = $p->get_tag('a')) {
  if ($t->[1]{href} =~ /^\/deputes\/fiche\//) {
    download_fiche($t->[1]{href});
  }
}

open PM, ">finmandats.pm";
print PM '$legislature = '."$legislature;\n";
$a->get("https://www2.assemblee-nationale.fr/deputes/liste/clos");
$content = $a->content;
$p = HTML::TokeParser->new(\$content);
while ($t = $p->get_tag('td')) {
  $t = $p->get_tag('a');
  if ($t->[1]{href} && $t->[1]{href} =~ /deputes\/fiche/) {
    $id = download_fiche($t->[1]{href});
    if (!$id) { next; }
    $ret = system("grep -i '>Mandat clos<' html/$id > /dev/null");
    if (! $ret) {
      $t = $p->get_tag('td');
      $t = $p->get_tag('td');
      $t = $p->get_tag('td');
      $t = $p->get_tag('td');
      $t = $p->get_tag('td');
      $t = $p->get_tag('td');
      $t = $p->get_text('/td');
      $t =~ s/[^\d\/]//g;
      print PM "\$fin_mandat{'$id'} = '$t';\n";
    }
  }
}

