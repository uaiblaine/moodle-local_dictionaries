# get_reference_labels: format_string runs with its default escaping.
s~util::format_string\(\(string\)\$label, \$context, true, \['escape' => false\]\)~util::format_string((string)\$label, \$context, true)~s;
