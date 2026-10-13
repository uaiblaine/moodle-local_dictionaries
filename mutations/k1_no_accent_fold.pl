# Keep the diacritics: the \p{Mn} strip is dropped, so 'são' and 'sao' become two codes.
s~\$code = preg_replace\('/\\p\{Mn\}\+/u', '', \$decomposed\) \?\? \$decomposed;~\$code = \$decomposed;~s;
