<?php

use ElseifAB\IDKollen\API\ApiKeyManager;

/*
Plugin Name: Login för test av id-kollen
Description: Placera shortcode [logga-in] i en text
*/

add_shortcode('logga-in', function () {
    $url = rest_url("elseifab/id-kollen/v1/auth");

    $result = "<form action=\"{$url}\" method=\"post\">";
    $result .= "<input type=\"text\" name=\"pnr\" placeholder=\"Personnummer\" />";
    $result .= "<br/>";
    $result .= "<input type=\"submit\" value=\"Logga in\" />";
    $result .= "";
    $result .= "";
    $result .= "";
    $result .= "</form>";
    $result .= "<br/>";

    return $result;
});
