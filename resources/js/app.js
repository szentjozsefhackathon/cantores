/*
   The bundle every page carries. It holds the stylesheet and the handful of
   things that can appear anywhere; the engraving code — Aretino, ABC, ChordPro,
   the booklet layout engine — is an entry point of its own, asked for by the
   five views that actually draw music (see partials/head.blade.php).
*/
import '../css/app.css';
import './turnstile.js';
