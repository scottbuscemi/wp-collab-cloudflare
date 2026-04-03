// Shim that re-exports WordPress's bundled Yjs (exposed as wp.sync.Y by wp-sync).
// This prevents bundling a duplicate copy of the library.
// Only needed for y-protocols/awareness which imports 'yjs' for type annotations.
const Y = window.wp?.sync?.Y || {};
export default Y;
export const Doc = Y.Doc;
export const applyUpdate = Y.applyUpdate;
export const encodeStateVector = Y.encodeStateVector;
export const encodeStateAsUpdate = Y.encodeStateAsUpdate;
