# upsert_items(): the single-batch call fires no items_imported event.
s~(if \(!empty\(\$items\)\) \{\n\s+)\$this->trigger_items_imported\(\$domain, \$counts\);~$1;~s;
