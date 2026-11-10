# code_key stops recomposing to NFC: a Hangul syllable stays two or three jamo in the key.
s~\$recomposed = \\Normalizer::normalize\(\$code, \\Normalizer::FORM_C\);~\$recomposed = null;~s;
