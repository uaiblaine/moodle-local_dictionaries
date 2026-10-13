# CSV: the UTF-8 byte order mark is not stripped, so it hides the header.
s~\$content = core_text::trim_utf8_bom\(\$csvcontent\);~\$content = \$csvcontent;~s;
