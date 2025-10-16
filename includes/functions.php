<?php
// includes/functions.php

// Function untuk format text dengan huruf pertama kapital
function formatText($text) {
    if (empty($text)) return $text;
    return ucfirst(strtolower(trim($text)));
}

// Function untuk format status dengan warna yang sesuai
function getStatusBadge($status) {
    $statusLower = strtolower($status);
    $formattedStatus = formatText($status);
    
    $colorClasses = [
        'completed' => 'bg-green-500',
        'in progress' => 'bg-yellow-500',
        'planned' => 'bg-blue-500'
    ];
    
    $color = $colorClasses[$statusLower] ?? 'bg-gray-500';
    
    return '<span class="font-semibold text-white px-2 py-1 rounded-full text-xs ' . $color . '">' . $formattedStatus . '</span>';
}
?>