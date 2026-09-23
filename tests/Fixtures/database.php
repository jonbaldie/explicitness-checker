<?php

function count_users(\mysqli $connection): \mysqli_result|bool {
    return mysqli_query($connection, 'SELECT COUNT(*) FROM users');
}
