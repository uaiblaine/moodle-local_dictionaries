# upsert_domain(): no domain_created event.
s~domain_created::create\(\[.*?\]\)->trigger\(\);~;~s;
