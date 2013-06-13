<?php
    function CLI_squat_go() {
        while(true):
        $lexeme = array(
            'buy' => '[buy|kupi|kupiti|pokupay]',
            'pen' => '[pen|pencil|ru4ka|ruchka]',
            'tld' => '[ru|org|com|net]'
        );
        $default = include(OTHER_ROOT . '/generator.db');
        $domain = generator('@rotate({[@buy|(0.3)]}{@pen}).@tld', $default + $lexeme);
        if(WhoIs::lookup($domain)) ;
        else echo $domain, chr(10);
        endwhile;
    }
