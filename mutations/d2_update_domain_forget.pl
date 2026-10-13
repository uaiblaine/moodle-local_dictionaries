# upsert_domain() update path no longer drops the cached domain record (it carries the visibility).
s~(\$DB->update_record\(domains::DOMAIN_TABLE, \$update\);\n\s+)domains::forget\(\$shortname\);~$1;~s;
