<?php

namespace VUMC\DataDictionarySQLSanitizer;

error_log("IN");

error_log(json_encode($_REQUEST,JSON_PRETTY_PRINT));
echo "success";