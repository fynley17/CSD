<?php

namespace App\Models;

/**
 * Administrator, extends Lecturer because an admin can do every lecturer job as well
 * on top of that they manage subjects and lecturer accounts
 */
class Administrator extends Lecturer
{
}
