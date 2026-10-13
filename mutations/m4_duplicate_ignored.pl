# normalise_items(): a superseded duplicate in one batch is no longer counted as ignored.
s~(an earlier item with the same code is superseded by this one\.\n\s+)\$ignored\+\+;~$1;~is;
