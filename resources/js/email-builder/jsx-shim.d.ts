import type { JSX as ReactJSX } from 'react';

declare global {
    namespace JSX {
        type Element = ReactJSX.Element;
        type ElementClass = ReactJSX.ElementClass;
        type IntrinsicElements = ReactJSX.IntrinsicElements;
    }
}

export {};
