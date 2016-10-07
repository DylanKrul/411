"use strict";
define(function(require) {
    var CodeMirror = require('codemirror');
    CodeMirror.defineMode('ecl', function() {

        var words = {};
        function define(style, string) {
            var split = string.split(' ');
            for(var i = 0; i < split.length; i++) {
                words[split[i]] = style;
            }
        }

        // Atoms
        define('atom', 'NOT AND OR TO true false');

        // Keywords
        define('builtin', 'if else for');

        // Commands
        define('keyword', 'set es agg load map join sort head tail filter count');

        function tokenBase(stream, state) {
            if (stream.eatSpace()) return null;

            var sol = stream.sol();
            var ch = stream.next();

            if (ch === '\\') {
                stream.next();
                return null;
            }
            if (ch === '\'' || ch === '"' || ch === '`') {
                state.tokens.unshift(tokenString(ch));
                return tokenize(stream, state);
            }
            if (ch === '#') {
                stream.skipToEnd();
                return 'comment';
            }
            if (ch === '$') {
                state.tokens.unshift(tokenDollar);
                return tokenize(stream, state);
            }
            if (ch === '+' || ch === '=') {
                return 'operator';
            }
            if (ch === '-') {
                stream.eat('-');
                stream.eatWhile(/\w/);
                return 'attribute';
            }
            if (/\d/.test(ch)) {
                stream.eatWhile(/\d/);
                if(stream.eol() || !/\w/.test(stream.peek())) {
                    return 'number';
                }
            }
            stream.eatWhile(/[\w-]/);
            var cur = stream.current();
            if (stream.peek() === '=' && /\w+/.test(cur)) return 'def';
            return words.hasOwnProperty(cur) ? words[cur] : null;
        }

        function tokenString(quote) {
            return function(stream, state) {
                var next, end = false, escaped = false;
                /* jshint -W041 */
                while ((next = stream.next()) != null) {
                /* jshint +W041 */
                    if (next === quote && !escaped) {
                        end = true;
                        break;
                    }
                    if (next === '$' && !escaped && quote !== '\'') {
                        escaped = true;
                        stream.backUp(1);
                        state.tokens.unshift(tokenDollar);
                        break;
                    }
                    escaped = !escaped && next === '\\';
                }
                if (end || !escaped) {
                    state.tokens.shift();
                }
                return (quote === '`' || quote === ')' ? 'quote' : 'string');
            };
        }

        var tokenDollar = function(stream, state) {
            if (state.tokens.length > 1) stream.eat('$');
            var ch = stream.next(), hungry = /\w/;
            if (ch === '{') hungry = /[^}]/;
            if (ch === '(') {
                state.tokens[0] = tokenString(')');
                return tokenize(stream, state);
            }
            if (!/\d/.test(ch)) {
                stream.eatWhile(hungry);
                stream.eat('}');
            }
            state.tokens.shift();
            return 'def';
        };

        function tokenize(stream, state) {
            return (state.tokens[0] || tokenBase) (stream, state);
        }

        return {
            startState: function() {return {tokens:[]};},
            token: function(stream, state) {
                return tokenize(stream, state);
            },
            lineComment: '#',
            fold: "brace"
        };
    });

    CodeMirror.defineMIME('text/x-ecl', 'ecl');
});
