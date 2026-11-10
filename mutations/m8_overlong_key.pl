# normalise_items(): only the code's length is checked, not its key's.
s~if \(!keys::code_fits\(\$item\['code'\], \$codekey\)\) \{~if (core_text::strlen(\$item['code']) > keys::MAX_KEY_LENGTH) {~s;
