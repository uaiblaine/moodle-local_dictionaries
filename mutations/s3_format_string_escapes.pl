# search_items: format_string runs with its default escaping, so '&' leaves as '&amp;'.
s~util::format_string\(\$item\['label'\], \$context, true, \['escape' => false\]\)~util::format_string(\$item['label'], \$context, true)~s;
