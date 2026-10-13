# Key the result by the folded code key instead of the caller's spelling.
s~\$results\[\(string\)\$code\] = \$labelsbykey\[\$codekey\];~\$results[(string)\$codekey] = \$labelsbykey[\$codekey];~s;
