// Field type for CRUD generator
export interface CrudField {
  name: string;
  type: string;
  required: boolean;
  unique: boolean;
  default: string;
  min: string;
  max: string;
  enumValues: string;
  showIn: string[]; // e.g. ['add', 'edit', 'show', 'table']
}

// Relationship type for CRUD generator
export interface CrudRelationship {
  name: string; // e.g. 'user', 'category', 'comments'
  type: 'belongsTo' | 'hasOne' | 'hasMany' | 'belongsToMany' | 'hasManyThrough';
  relatedModel: string; // e.g. 'User', 'Category', 'Comment'
  foreignKey?: string; // e.g. 'user_id' (for belongsTo)
  localKey?: string; // e.g. 'id' (default)
  pivotTable?: string; // for belongsToMany
  foreignPivotKey?: string; // for belongsToMany
  relatedPivotKey?: string; // for belongsToMany
  withTimestamps?: boolean; // for belongsToMany pivot table
  withPivot?: string[]; // for belongsToMany pivot columns
}

// Generator options (permissions, translations, etc.)
export interface GeneratorOptions {
  permissions: boolean;
  translations: boolean;
}

// SelectMultiple component props
export interface SelectMultipleProps {
  options: string[];
  value: string[];
  onChange: (value: string[]) => void;
}
