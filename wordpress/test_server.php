<?php
file_put_contents('/tmp/server_vars.json', json_encode($_SERVER));
echo "OK";
