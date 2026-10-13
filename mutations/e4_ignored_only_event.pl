# upsert_items_chunked(): a call whose items were all dropped before any write fires no event.
s~if \(\$pendingignored > 0\) \{~if (false) {~s;
