# check_parents(): an unknown parent is no longer refused up front (write_parents() still throws, inside
# the transaction, so only the chunked path - which has committed earlier chunks by then - shows it).
s~if \(!empty\(\$missing\)\) \{~if (false) {~s;
