<?php
namespace App\Controller;

use PDO;

abstract class BaseController
{
    public function __construct(protected PDO $db) {}
}
