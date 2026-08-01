@AGENTS.md

## Claude Code

Las instrucciones del proyecto viven en `AGENTS.md`, que este fichero importa
para que las lean por igual Claude Code y el resto de agentes. No dupliques
contenido aquí: añádelo allí.

Las skills están en `.agents/skills/`, enlazadas desde `.claude/skills/`. Las de
WordPress y la de auditoría de seguridad son de terceros: consúltalas antes de
tocar hooks, la API REST, el `readme.txt` del directorio de plugins o el
Blueprint de Playground.
