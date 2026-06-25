<?php

namespace App\Helpers;

class PaginationHelper
{
    public static function paginate($reload, $page, $total_pages, $adjacents)
    {
        $prevlabel = "&lsaquo; Prev";
        $nextlabel = "Next &rsaquo;";
        $out = '<ul class="pagination pagination-large">';
        
        // Previous
        if ($page == 1) {
            $out .= "<li class='disabled'><span><a>$prevlabel</a></span></li>";
        } else {
            $out .= "<li><span><a href='javascript:void(0);' onclick='load(" . ($page - 1) . ")'>$prevlabel</a></span></li>";
        }
        
        // First
        if ($page > ($adjacents + 1)) {
            $out .= "<li><a href='javascript:void(0);' onclick='load(1)'>1</a></li>";
        }
        
        // Interval
        if ($page > ($adjacents + 2)) {
            $out .= "<li class='disabled'><a>...</a></li>";
        }
        
        // Pages
        $pmin = ($page > $adjacents) ? ($page - $adjacents) : 1;
        $pmax = ($page < ($total_pages - $adjacents)) ? ($page + $adjacents) : $total_pages;
        for ($i = $pmin; $i <= $pmax; $i++) {
            if ($i == $page) {
                $out .= "<li class='active'><a>$i</a></li>";
            } else {
                $out .= "<li><a href='javascript:void(0);' onclick='load(" . $i . ")'>$i</a></li>";
            }
        }
        
        // Interval
        if ($page < ($total_pages - $adjacents - 1)) {
            $out .= "<li class='disabled'><a>...</a></li>";
        }
        
        // Last
        if ($page < ($total_pages - $adjacents)) {
            $out .= "<li><a href='javascript:void(0);' onclick='load($total_pages)'>$total_pages</a></li>";
        }
        
        // Next
        if ($page < $total_pages) {
            $out .= "<li><span><a href='javascript:void(0);' onclick='load(" . ($page + 1) . ")'>$nextlabel</a></span></li>";
        } else {
            $out .= "<li class='disabled'><span><a>$nextlabel</a></span></li>";
        }
        
        $out .= "</ul>";
        return $out;
    }
}