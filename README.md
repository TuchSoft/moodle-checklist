## Moodle Checklist: Core Component Definitions

This document outlines the core components and their defined responsibilities within the Moodle Checklist project. These definitions ensure clear understanding and consistent implementation across the system.

### Process

A **Process** represents a **connection to an external command or tool that doesn't run on PHP**. Its primary function is to encapsulate the logic for interacting with these outside executables, like shell commands, system utilities, or other programming language scripts. An `Process` can only be invoked by an `Utils`.

---

### Utils

An **Utils** defines a **simple, definite, and non-validating operation**. Its purpose is to perform a specific task that doesn't inherently report quality-related issues such as warnings, errors, or tips. An `Utils` is solely operational, executing a defined task. It is the only component capable of directly invoking a `Process`.

---

### Validator

A **Validator** is a class designed for a **single, atomic validation check**. Its objective is to verify a specific condition and report any identified issues, including errors, warnings, or tips. Each `Validator` is concise, focused on a singular check, and identified by a unique string "code." Validators are designed for reusability across multiple `Check` instances and must report a primary issue, along with any secondary issues encountered during data acquisition for the validation.

---

### Check

A **Check** is a **logical collection of `Validator` instances**. Its role is to group related individual validations to assess a specific "part" of a Moodle plugin (e.g., README compliance, language file integrity, coding standards). `Check` instances serve as the primary executable units during a Moodle Checklist run.