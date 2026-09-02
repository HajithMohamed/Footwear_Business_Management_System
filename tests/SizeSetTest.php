<?php

use App\Models\SizeSet;

eq(4, SizeSet::pairsFromLabel('05X08'), 'Size set derives four pairs from the supplier X format');
eq(4, SizeSet::pairsFromLabel('5-8'), 'Size set still derives four pairs from a hyphenated catalogue range');
