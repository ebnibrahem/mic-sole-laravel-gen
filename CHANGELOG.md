# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2025-01-XX

### Fixed

- Fixed stub path issues in all Commands and Services (21 locations in 7 files)
- Fixed authentication files not being generated (AuthenticatedSessionController, PasswordResetLinkController, NewPasswordController)
- Fixed blade views not being generated (login.blade.php, layouts, components)
- Fixed authentication requests not being generated (LoginRequest, RegisterRequest, PasswordResetRequest)
- Fixed routes/auth.php not being updated from stub

### Changed

- Added Helper Methods in MicSoleLaravelGenServiceProvider:
  - `getPackagePath()` - Get package root path
  - `getTemplatesPath()` - Get templates directory path
  - `getStubPath($stubPath)` - Get stub file path
- Updated all Commands to use `getStubPath()` instead of `base_path('mic-sole-laravel-gen/...')`
- Updated all Services to use `getTemplatesPath()` instead of hardcoded path
- Improved routes/auth.php update logic to handle empty files

## [1.0.0] - 2025-12-22

### Added

- Initial release of Mic Sole Laravel CRUD Generator
- Complete CRUD generation for Laravel (Model, Controller, Service, Request, Resource, Migration, Seeder, Factory, Policy)
- Vue 3 Dashboard frontend generation (Pages, Components, Types, Routes)
- React UI for CRUD generation (React 19 + TypeScript + Tailwind CSS)
- File tracking and rollback system
- Template synchronization system
- Permission system integration
- Translation system support
- Bulk operations support (delete, activate, deactivate)
- Export functionality (Excel, PDF, Image)
- Pagination, filtering, and sorting support
- Laravel Pest test generation
- Relationship support (belongsTo, hasOne, hasMany, belongsToMany)
- Dashboard initialization command
- UI installation command
- Template sync command
- Status and rollback commands

### Features

- ✅ Backend file generation (9 file types)
- ✅ Frontend file generation (8 file types)
- ✅ React-based CRUD generator UI
- ✅ Vue 3 dashboard with PrimeVue
- ✅ TypeScript support
- ✅ Arabic language support
- ✅ Permission-based access control
- ✅ Automatic API route registration
- ✅ Automatic Vue route registration
- ✅ File tracking and rollback
- ✅ Template synchronization
- ✅ Test generation with Pest
