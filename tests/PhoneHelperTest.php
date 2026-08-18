<?php

require_once BASE_PATH . '/app/Helpers/helpers.php';

eq('+94771234567', sri_lankan_phone('077 123 4567'), 'local mobile is stored with +94');
eq('+94771234567', sri_lankan_phone('+94 77 123 4567'), 'international mobile stays normalized');
eq('+94112345678', sri_lankan_phone('011-234-5678'), 'landline is stored with +94');
eq('94771234567', whatsapp_phone('0771234567'), 'WhatsApp number omits plus sign');
eq(null, sri_lankan_phone('1234'), 'invalid phone is rejected');
