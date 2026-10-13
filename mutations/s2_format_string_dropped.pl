# search_items: labels leave the service raw.
s~util::format_string\(\$item\['label'\], \$context, true, \['escape' => false\]\)~\$item['label']~s;
