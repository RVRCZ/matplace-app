<?php

/*
 * Robots the statistics must not count as people, beyond what a User-Agent says by itself (App\Support\Bots keeps
 * the rules, docs/AP.md §14, docs/O.md). Everything here can be changed in .env without a deploy.
 */
return [
    /*
     * Address blocks of data centres a robot farm came from, as CIDR ("203.0.113.0/24, 2001:db8::/32"). A page asked
     * for from one of them is a `crawl` of the robot `datacenter`, never a visit. Take the blocks from the web
     * server's log (12 Nov 2026: 1 128 requests of one repeated "Firefox 159" header came from one block).
     */
    'networks' => array_values(array_filter(array_map('trim', explode(',', (string) env('BOT_NETWORKS', ''))))),

    /*
     * Whole or partial User-Agent strings that are a robot's although they read like a browser, separated by "|"
     * (a User-Agent has commas). Only for a string no real browser sends; a current browser version is NOT one.
     */
    'agents' => array_values(array_filter(array_map('trim', explode('|', (string) env('BOT_AGENTS', ''))))),

    /*
     * One repeated header from one address block: this many pages in a clock hour asked for without our cookie by the
     * same User-Agent from the same network (IPv4 /24, IPv6 /48) are one machine; what comes after is a `crawl` of
     * the robot `burst`. A browser keeps the cookie from its first page on, so a person is counted once however
     * much they read; 0 switches the rule off.
     */
    'burst_per_hour' => (int) env('BOT_BURST_PER_HOUR', 40),
];
