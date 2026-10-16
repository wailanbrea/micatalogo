# Rollback de cambios del cierre

- Los cambios actuales no han sido desplegados ni aplicados a producción.
- En caso de rechazo antes del push, revertir únicamente los commits de esta tarea en la rama de trabajo; conservar cualquier otro cambio ajeno.
- Después de un push, revertir commits específicos con `git revert <sha>` en la misma rama y validar de nuevo Pest/Gradle.
- No revertir o limpiar datos comerciales: no se modificó ninguna base productiva ni se ejecutaron migraciones.
- No usar `reset --hard`, force-push ni operaciones destructivas sobre las ramas.
