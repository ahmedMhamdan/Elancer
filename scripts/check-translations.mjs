import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import ts from 'typescript';

const root = path.resolve(import.meta.dirname, '..');
const sourceRoot = path.join(root, 'resources/js');
const parse = (file) =>
    ts.createSourceFile(
        file,
        fs.readFileSync(file, 'utf8'),
        ts.ScriptTarget.Latest,
        true,
    );
const catalogFile = parse(path.join(sourceRoot, 'lib/translations.ts'));
const catalog = new Map();
function visit(node, callback) {
    callback(node);
    ts.forEachChild(node, (child) => visit(child, callback));
}
visit(catalogFile, (node) => {
    if (ts.isPropertyAssignment(node) && ts.isStringLiteral(node.initializer)) {
        const key =
            ts.isIdentifier(node.name) || ts.isStringLiteral(node.name)
                ? node.name.text
                : null;
        if (key !== null) {
            assert.ok(!catalog.has(key), `Duplicate translation: ${key}`);
            catalog.set(key, node.initializer.text);
        }
    }
});
const placeholders = (value) =>
    [...value.matchAll(/:[a-zA-Z_]+/g)]
        .map(([token]) => token)
        .sort((a, b) => a.localeCompare(b));
for (const [key, value] of catalog) {
    assert.ok(
        value.trim() && !/\?{2,}|\uFFFD/.test(value),
        `Damaged or empty translation: ${key}`,
    );
    assert.deepEqual(
        placeholders(value),
        placeholders(key),
        `Placeholder mismatch: ${key}`,
    );
}
// Follow only values returned for display, not comparisons such as error class names.
function visitMessages(node, callback) {
    if (ts.isStringLiteral(node)) callback(node);
    else if (ts.isConditionalExpression(node)) {
        visitMessages(node.whenTrue, callback);
        visitMessages(node.whenFalse, callback);
    } else if (ts.isParenthesizedExpression(node))
        visitMessages(node.expression, callback);
    else if (
        ts.isBinaryExpression(node) &&
        [
            ts.SyntaxKind.BarBarToken,
            ts.SyntaxKind.QuestionQuestionToken,
        ].includes(node.operatorToken.kind)
    ) {
        visitMessages(node.left, callback);
        visitMessages(node.right, callback);
    }
}
let calls = 0;
for (const entry of fs.readdirSync(sourceRoot, { recursive: true })) {
    if (
        !/\.(ts|tsx)$/.test(entry) ||
        /^(actions|routes|wayfinder)[/\\]/.test(entry)
    )
        continue;
    const file = parse(path.join(sourceRoot, entry));
    visit(file, (node) => {
        if (
            !ts.isCallExpression(node) ||
            !ts.isIdentifier(node.expression) ||
            node.expression.text !== 't' ||
            !node.arguments[0]
        )
            return;
        visitMessages(node.arguments[0], (argument) => {
            if (ts.isStringLiteral(argument) && argument.text) {
                assert.ok(
                    catalog.has(argument.text),
                    `${String(entry)}: missing Arabic copy for ${argument.text}`,
                );
                calls++;
            }
        });
    });
}
// These profiles are authored site examples, not member content.
visit(parse(path.join(sourceRoot, 'data/home-samples.ts')), (node) => {
    if (
        ts.isPropertyAssignment(node) &&
        ts.isIdentifier(node.name) &&
        [
            'title',
            'description',
            'role',
            'intro',
            'project',
            'projectDescription',
        ].includes(node.name.text) &&
        ts.isStringLiteral(node.initializer)
    ) {
        assert.ok(
            catalog.has(node.initializer.text),
            `Untranslated sample content: ${node.initializer.text}`,
        );
    }
});
console.log(
    `Arabic coverage passed: ${catalog.size} messages, ${calls} literal uses, matching placeholders and sample copy.`,
);
