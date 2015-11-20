<?php

namespace BatchWrite;

interface DBAdapter
{
    public function query($sql, $params);
}
