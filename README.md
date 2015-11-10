Meta Pocket Extension
======================
Collects lots of data

1. put meta.yml to your config directory
2. use site_meta(meta_sequence) . sequence is 'global.suteurl' or 'global.meta.description'
	for example: {{ site_meta('contacts.phone')}}
3. use route_meta(meta_sequence) 
	for example: {{ route_meta('contacts.phone')}}
4. use route_meta_contexted('title', post )
	if `route_meta('title')` gives something like `"site title - about %context.page_heading%"`
	so if you launch route_meta_contexted('title', post), where `post['page_heading'] == 'beautiful horses' `, 
	it will give you `"site title - about beautiful horses"`
	
 
Also i made aliases for sequences, so you can use `route_meta('comment')` for example.



_started 11.11.2015_

