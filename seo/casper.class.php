<?php
    class Casper {
        public static function go($url, $script, $settings = array()) {
            @ $ua = $settings['ua'];
            @ $echo = $settings['echo'];
            @ $scp = $settings['scp'];
            @ $verbose = intval($settings['verbose']);
            @ $loadImages = $settings['loadImages'];
            @ $noSecurity = $settings['noSecurity'];
            @ $cookie = $settings['cookie'];
            if($scp and $scp != '/') $scp = rtrim($scp, '/');
            else $scp = '/tmp';
            if(!$ua) $ua = getUserAgent();
            $jq = '/tmp/jquery-1.8.3.js';
            // http://code.jquery.com/jquery-1.8.3.js
            // window.jQuery = window.$ = jQuery;
            // window.j_q = jQuery;
            if(!is_file($jq) or !filesize($jq))
                trigger_error("INVALID JQ IN /TMP", E_USER_ERROR);
            //
            $loadImages = $loadImages ? 'loadImages: true' : 'loadImages: false';
            $noSecurity = $noSecurity ?
                'localToRemoteUrlAccessEnabled: true, XSSAuditingEnabled: false, webSecurityEnabled: false'
                    :
                'localToRemoteUrlAccessEnabled: false, XSSAuditingEnabled: true, webSecurityEnabled: true'
            ;
            $wrapper = "
                var _scc = 0
                var utils = require('utils')
                function rand(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min }
                var casper = require('casper').create({
                    verbose: {$verbose},
                    logLevel: 'debug',
                    clientScripts: ['{$jq}'],
                    pageSettings: { {$loadImages}, {$noSecurity} },
                    viewportSize: { width: 1280, height: 1024 },
                    onAlert: function(self, m) { self.echo(m) }
                });
                casper.on('page.error', function(msg, trace) {
                    casper.echo('--- MARKER OF JAVASCRIPT ERROR ---')
                    casper.echo(utils.serialize(trace))
                    casper.echo('Error: ' + msg, 'ERROR')
                    capture()
                });
                function assert(arg, echo) {
                    if(arg['success']) return
                    if(typeof echo == 'string') casper.echo(echo)
                    capture()
                    casper.exit()
                }
                function click(selector) {
                    var attr = 'cmn_' + rand(10000, 99999)
                    var length = casper.evaluate(function(selector, attr) {
                        j_q(selector).first().attr(attr, attr)
                        __utils__.echo(j_q(selector)[0].outerHTML)
                        return j_q(selector).length
                    }, selector, attr)
                    assert(casper.test.assert(length > 0, 'assert click element, selector: ' + selector))
                    casper.click('[' + attr + ']')
                    casper.evaluate(function(selector, attr) {
                        j_q(selector).first().removeAttr(attr)
                    }, selector, attr)
                }
                function thenClick(selector) {
                    casper.then(function() { click(selector) })
                }
                function wait(min, max) {
                    var sleep = rand(min, max) * 1000
                    casper.wait(sleep, function() {
                        casper.echo('SLEPT FOR ' + (sleep / 1000) + ' SECONDS')
                    })
                }
                function capture() {
                    var path = '{$scp}' + '/capture.' + (_scc++) + '.png'
                    casper.echo('save screen to ' + path)
                    casper.capture(path)
                }
                function thenCapture() {
                    casper.then(capture)
                }
                function imgToBase64(selector) {
                    return casper.evaluate(function(selector) {
                        var img = j_q(selector)
                        if(!img.length) return
                        img = img[0]

                        var canvas = document.createElement('canvas')
                        canvas.width = img.width
                        canvas.height = img.height
                        
                        var ctx = canvas.getContext('2d')
                        ctx.drawImage(img, 0, 0);

                        var dataURL = canvas.toDataURL('image/jpg')
                        return dataURL.replace(/^data:image\/(png|jpg);base64,/, '')
                    }, selector)
                }
                casper.userAgent('{$ua}')
                casper.start() // START
                wait(2, 4) // STANDARD WAIT #1
                casper.thenOpen('{$url}') // START URL
                wait(2, 4) // STANDARD WAIT #2
                // SCRIPT - BEGIN
                %s
                // SCRIPT - END
                casper.run()
            ";
            $script = sprintf($wrapper, $script);
            $rand = rand(0, 100);
            $file = "/tmp/casper.{$rand}.js";
            file_put_contents($file, $script);
            trigger_error("WRITE SCRIPT TO {$file}", E_USER_NOTICE);
            $ws = $noSecurity ? '--web-security=no' : '';
            $cf = $cookie ? "--cookies-file=\"/tmp/casper.cookie." . rand_from_string($cookie) . '"' : '';
            $cmd = "casperjs {$cf} {$ws} --output-encoding=utf8 '{$file}' 2>&1";
            trigger_error($cmd, E_USER_NOTICE);
            $pipe = popen($cmd, 'r');
            $content = '';
            while(!feof($pipe)) {
                $buffer = fread($pipe, 1);
                if($echo) echo $buffer;
                $content .= $buffer;
            }
            pclose($pipe);
            return $content;
        }
    }
