# rekey_items() finds the stale keys but never writes them.
s~\$DB->set_field\(domains::ITEM_TABLE, 'codekey', \$change\['codekey'\], \['id' => \$id\]\);~~s;
