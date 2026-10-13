# upsert_domain(): no domain_updated event.
s~domain_updated::create\(\[.*?\]\)->trigger\(\);~;~s;
