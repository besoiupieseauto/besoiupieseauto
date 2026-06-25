<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    // Table name
    protected $table = 'employees';

    // Primary key
    protected $primaryKey = 'EmployeeId';

    // Disable timestamps if they aren't part of the table structure
    public $timestamps = false;

    // Columns that are mass assignable
    protected $fillable = [
        'EmployeeId',
        'LastName',
        'FirstName',
        'Title',
        'TitleOfCourtesy',
        'BirthDate',
        'HireDate',
        'Address',
        'City',
        'Region',
        'PostalCode',
        'Country',
        'HomePhone',
        'Extension',
        'Photo',
        'Notes',
        'ReportsTo',
        'PhotoPath',
        'CI',
        'CiNr',
        'CNP',
    ];

    // Casts for specific data types
    protected $casts = [
        'BirthDate' => 'date',
        'HireDate' => 'date',
        'Photo' => 'binary', // Handle BLOB type
        'CiNr' => 'integer',
        'CNP' => 'string',
    ];

    // Define any relationships (if needed) here
}
