import React from "react";
import { createRoot } from "react-dom/client";
import CrudGeneratorApp from "./crud-generator/CrudGeneratorApp";

import '../css/mic-sole.css';

const rootElement = document.getElementById("crud-generator-root");
if (rootElement) {
    createRoot(rootElement).render(
      <React.StrictMode>
          <CrudGeneratorApp />
      </React.StrictMode>
    );
}
