# domain_rows: the name leaves raw, so multilang spans are never resolved.
s~format_string\(\(string\)\$domain->name, true, \['context' => \$context, 'escape' => false\]\)~(string)\$domain->name~s;
