# Skip the NFD decomposition: precomposed accents survive the \p{Mn} strip.
s~\\Normalizer::normalize\(\$code, \\Normalizer::FORM_D\)~\$code~s;
