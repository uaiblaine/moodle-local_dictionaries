# upsert_items_chunked(): no items_imported event per committed chunk.
s~(\$pendingignored = 0;\n\s+)\$this->trigger_items_imported\(\$domain, \$counts\);~$1;~s;
