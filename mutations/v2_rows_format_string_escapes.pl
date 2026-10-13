# domain_rows: format_string runs with its default escaping, so the double stash escapes '&' twice.
s~(format_string\(\(string\)\$domain->name, true, \['context' => \$context), 'escape' => false\]\)~$1])~s;
