<?php
// SEO هر صفحه/اردو/آلبوم/خبر؛ در نبود رکورد، schema خودکار ساخته می‌شود
require __DIR__.'/../includes/bootstrap.php';global $CFG;$p=db();
$t=['camp'=>'camps','album'=>'albums','news'=>'news','page'=>'pages'][$_GET['entity']??'']??null;if(!$t)out(false,'نامعتبر',null,404);
$s=$p->prepare("SELECT * FROM `$t` WHERE slug=? AND published=1");$s->execute([$_GET['slug']??'']);$r=$s->fetch();if(!$r)out(false,'یافت نشد',null,404);
$x=$p->prepare('SELECT * FROM seo WHERE entity=? AND entity_id=?');$x->execute([$_GET['entity'],$r['id']]);$o=$x->fetch()?:[];
$title=$o['seo_title']??$r['title'];$b=rtrim($CFG['site_url'],'/');
$schema=$o['schema_json']??json_encode(match($_GET['entity']){'camp'=>['@context'=>'https://schema.org','@type'=>'Event','name'=>$r['title'],'startDate'=>$r['start_date'],'endDate'=>$r['end_date'],'location'=>['@type'=>'Place','name'=>$r['location']]],'news'=>['@context'=>'https://schema.org','@type'=>'Article','headline'=>$r['title'],'datePublished'=>$r['created_at'],'author'=>$r['author']],default=>['@context'=>'https://schema.org','@type'=>'WebPage','name'=>$r['title']]},JSON_UNESCAPED_UNICODE);
out(true,'',['title'=>$title,'description'=>$o['meta_description']??($r['short_desc']??$r['summary']??$r['description']??''),'canonical'=>$o['canonical']??$b.'/'.$_GET['entity'].'/'.$r['slug'],'og_title'=>$o['og_title']??$title,'og_description'=>$o['og_description']??null,'og_image'=>$o['og_image']??null,'schema'=>$schema]);
