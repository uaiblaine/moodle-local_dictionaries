# upsert_items(): an empty batch fires an items_imported event too.
s~if \(!empty\(\$items\)\) \{~if (true) {~s;
