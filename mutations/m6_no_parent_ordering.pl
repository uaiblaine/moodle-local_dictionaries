# upsert_items_chunked(): the items are written in file order, so a child can land before its parent.
s~\$normalised = \$this->order_parents_first\(\$normalised\);~\$normalised = \$normalised;~;
