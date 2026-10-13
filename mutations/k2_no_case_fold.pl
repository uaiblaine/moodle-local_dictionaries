# code_key stops lower-casing: 'PT' and 'pt' become two codes.
s~return core_text::strtolower\(\$code\);~return \$code;~s;
