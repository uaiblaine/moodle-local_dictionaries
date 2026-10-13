# search(): the parent join matches the item to itself instead of to its parent.
s~p ON p\.id = i\.parentid~p ON p.id = i.id~s;
