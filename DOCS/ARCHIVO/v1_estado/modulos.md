# Módulos construidos en v1

Fecha de archivado: 2026-04-17

## Estructura de app/Modules

```
app/Modules
app/Modules/Asignaciones
app/Modules/Asignaciones/Application
app/Modules/Asignaciones/Application/Listeners
app/Modules/Asignaciones/Application/UseCases
app/Modules/Asignaciones/Domain
app/Modules/Asignaciones/Domain/Exceptions
app/Modules/Asignaciones/Infrastructure
app/Modules/Asignaciones/Infrastructure/Http
app/Modules/Asignaciones/Infrastructure/Persistence
app/Modules/Asignaciones/Infrastructure/Providers
app/Modules/Catalogos
app/Modules/Catalogos/Application
app/Modules/Catalogos/Application/DTOs
app/Modules/Catalogos/Application/Listeners
app/Modules/Catalogos/Application/UseCases
app/Modules/Catalogos/Domain
app/Modules/Catalogos/Domain/Contracts
app/Modules/Catalogos/Domain/Entities
app/Modules/Catalogos/Domain/Events
app/Modules/Catalogos/Domain/Exceptions
app/Modules/Catalogos/Domain/ValueObjects
app/Modules/Catalogos/Infrastructure
app/Modules/Catalogos/Infrastructure/Adapters
app/Modules/Catalogos/Infrastructure/Console
app/Modules/Catalogos/Infrastructure/Http
app/Modules/Catalogos/Infrastructure/Persistence
app/Modules/Catalogos/Infrastructure/Providers
app/Modules/Clientes
app/Modules/Clientes/Application
app/Modules/Clientes/Application/DTOs
app/Modules/Clientes/Application/Exceptions
app/Modules/Clientes/Application/Listeners
app/Modules/Clientes/Application/UseCases
app/Modules/Clientes/Domain
app/Modules/Clientes/Domain/Contracts
app/Modules/Clientes/Domain/Entities
app/Modules/Clientes/Domain/Events
app/Modules/Clientes/Domain/Exceptions
app/Modules/Clientes/Domain/ValueObjects
app/Modules/Clientes/Infrastructure
app/Modules/Clientes/Infrastructure/Console
app/Modules/Clientes/Infrastructure/Http
app/Modules/Clientes/Infrastructure/Persistence
app/Modules/Clientes/Infrastructure/Providers
app/Modules/Contactos
app/Modules/Contactos/Application
app/Modules/Contactos/Application/DTOs
app/Modules/Contactos/Application/Listeners
app/Modules/Contactos/Application/UseCases
app/Modules/Contactos/Domain
app/Modules/Contactos/Domain/Contracts
app/Modules/Contactos/Domain/Entities
app/Modules/Contactos/Domain/Events
app/Modules/Contactos/Domain/Exceptions
app/Modules/Contactos/Domain/ValueObjects
app/Modules/Contactos/Infrastructure
app/Modules/Contactos/Infrastructure/Console
app/Modules/Contactos/Infrastructure/Http
app/Modules/Contactos/Infrastructure/Persistence
app/Modules/Contactos/Infrastructure/Providers
app/Modules/Gestiones
app/Modules/Gestiones/Application
app/Modules/Gestiones/Application/DTOs
app/Modules/Gestiones/Application/Listeners
app/Modules/Gestiones/Application/UseCases
app/Modules/Gestiones/Domain
app/Modules/Gestiones/Domain/Contracts
app/Modules/Gestiones/Domain/Entities
app/Modules/Gestiones/Domain/Events
app/Modules/Gestiones/Domain/Exceptions
app/Modules/Gestiones/Domain/ValueObjects
app/Modules/Gestiones/Infrastructure
app/Modules/Gestiones/Infrastructure/Console
app/Modules/Gestiones/Infrastructure/Http
app/Modules/Gestiones/Infrastructure/Persistence
app/Modules/Gestiones/Infrastructure/Providers
app/Modules/Productos
app/Modules/Productos/Application
app/Modules/Productos/Application/DTOs
app/Modules/Productos/Application/Listeners
app/Modules/Productos/Application/UseCases
app/Modules/Productos/Domain
app/Modules/Productos/Domain/Contracts
app/Modules/Productos/Domain/Entities
app/Modules/Productos/Domain/Events
app/Modules/Productos/Domain/Exceptions
app/Modules/Productos/Domain/ValueObjects
app/Modules/Productos/Infrastructure
app/Modules/Productos/Infrastructure/Console
app/Modules/Productos/Infrastructure/Http
app/Modules/Productos/Infrastructure/Persistence
app/Modules/Productos/Infrastructure/Providers
app/Modules/Promesas
app/Modules/Promesas/Application
app/Modules/Promesas/Application/DTOs
app/Modules/Promesas/Application/Listeners
app/Modules/Promesas/Application/UseCases
app/Modules/Promesas/Domain
app/Modules/Promesas/Domain/Contracts
app/Modules/Promesas/Domain/Entities
app/Modules/Promesas/Domain/Events
app/Modules/Promesas/Domain/Exceptions
app/Modules/Promesas/Domain/ValueObjects
app/Modules/Promesas/Infrastructure
app/Modules/Promesas/Infrastructure/Console
app/Modules/Promesas/Infrastructure/Http
app/Modules/Promesas/Infrastructure/Persistence
app/Modules/Promesas/Infrastructure/Providers
app/Modules/Reportes
app/Modules/Reportes/Infrastructure
app/Modules/Reportes/Infrastructure/Http
app/Modules/Reportes/Infrastructure/Providers
app/Modules/Usuarios
app/Modules/Usuarios/Infrastructure
app/Modules/Usuarios/Infrastructure/Persistence
app/Modules/Usuarios/Infrastructure/Providers
```

## Archivos PHP en app/Modules (81 total)

- Asignaciones/Application/Listeners/IniciarTrabajoDesdeGestion.php
- Asignaciones/Application/UseCases/CerrarAsignacion.php
- Asignaciones/Domain/Exceptions/TransicionAsignacionInvalida.php
- Asignaciones/Infrastructure/Http/Livewire/Bandeja.php
- Asignaciones/Infrastructure/Persistence/Models/AsignacionModel.php
- Asignaciones/Infrastructure/Providers/AsignacionesServiceProvider.php
- Catalogos/Infrastructure/Adapters/ConsultaResultadoEloquent.php
- Catalogos/Infrastructure/Persistence/Models/CanalModel.php
- Catalogos/Infrastructure/Persistence/Models/CausaMoraModel.php
- Catalogos/Infrastructure/Persistence/Models/MotivoNoContactoModel.php
- Catalogos/Infrastructure/Persistence/Models/ResultadoModel.php
- Catalogos/Infrastructure/Persistence/Models/TipoGestionModel.php
- Catalogos/Infrastructure/Persistence/Models/TipoIdentificacionModel.php
- Catalogos/Infrastructure/Providers/CatalogosServiceProvider.php
- Clientes/Application/DTOs/RegistrarClienteInput.php
- Clientes/Application/DTOs/RegistrarClienteOutput.php
- Clientes/Application/Exceptions/IdentificacionYaExistente.php
- Clientes/Application/UseCases/RegistrarCliente.php
- Clientes/Domain/Contracts/ClienteRepository.php
- Clientes/Domain/Entities/Cliente.php
- Clientes/Domain/Exceptions/DatosClienteInvalidos.php
- Clientes/Domain/ValueObjects/Identificacion.php
- Clientes/Domain/ValueObjects/TipoPersona.php
- Clientes/Infrastructure/Http/Livewire/CrearCliente.php
- Clientes/Infrastructure/Persistence/Models/ClienteModel.php
- Clientes/Infrastructure/Persistence/Repositories/EloquentClienteRepository.php
- Clientes/Infrastructure/Providers/ClientesServiceProvider.php
- Contactos/Infrastructure/Persistence/Models/ContactoModel.php
- Contactos/Infrastructure/Providers/ContactosServiceProvider.php
- Gestiones/Application/DTOs/RegistrarGestionInput.php
- Gestiones/Application/DTOs/RegistrarGestionOutput.php
- Gestiones/Application/UseCases/RegistrarGestion.php
- Gestiones/Domain/Contracts/ConsultaResultado.php
- Gestiones/Domain/Contracts/GestionRepository.php
- Gestiones/Domain/Entities/Gestion.php
- Gestiones/Domain/Events/GestionRegistrada.php
- Gestiones/Domain/Exceptions/CausaMoraRequerida.php
- Gestiones/Domain/Exceptions/PromesaRequerida.php
- Gestiones/Domain/ValueObjects/BanderasResultado.php
- Gestiones/Domain/ValueObjects/DatosPromesa.php
- Gestiones/Domain/ValueObjects/DuracionSegundos.php
- Gestiones/Domain/ValueObjects/SnapshotGestion.php
- Gestiones/Infrastructure/Http/Livewire/BuscadorGlobal.php
- Gestiones/Infrastructure/Http/Livewire/NuevaGestion.php
- Gestiones/Infrastructure/Http/Livewire/VistaDeTrabajo.php
- Gestiones/Infrastructure/Persistence/Models/GestionModel.php
- Gestiones/Infrastructure/Persistence/Repositories/EloquentGestionRepository.php
- Gestiones/Infrastructure/Providers/GestionesServiceProvider.php
- Productos/Application/Listeners/ActualizarBanderaPromesaVigente.php
- Productos/Application/Listeners/ActualizarDesnormalizadosDesdeGestion.php
- Productos/Application/Listeners/RecalcularBanderaPromesaVigente.php
- Productos/Domain/ValueObjects/DiasMora.php
- Productos/Infrastructure/Persistence/Models/ProductoModel.php
- Productos/Infrastructure/Providers/ProductosServiceProvider.php
- Promesas/Application/DTOs/CrearPromesaDesdeGestionInput.php
- Promesas/Application/DTOs/ResolverPromesaInput.php
- Promesas/Application/Listeners/CrearPromesaAlRegistrarGestion.php
- Promesas/Application/UseCases/CancelarPromesa.php
- Promesas/Application/UseCases/CrearPromesaDesdeGestion.php
- Promesas/Application/UseCases/MarcarPromesaCumplida.php
- Promesas/Application/UseCases/MarcarPromesaRota.php
- Promesas/Domain/Contracts/PromesaRepository.php
- Promesas/Domain/Entities/Promesa.php
- Promesas/Domain/Events/EventoPromesaResuelta.php
- Promesas/Domain/Events/PromesaCancelada.php
- Promesas/Domain/Events/PromesaCreada.php
- Promesas/Domain/Events/PromesaCumplida.php
- Promesas/Domain/Events/PromesaRota.php
- Promesas/Domain/Exceptions/TransicionPromesaInvalida.php
- Promesas/Domain/ValueObjects/EstadoPromesa.php
- Promesas/Domain/ValueObjects/FechaPromesa.php
- Promesas/Domain/ValueObjects/MontoPromesa.php
- Promesas/Infrastructure/Http/Livewire/ResolverPromesa.php
- Promesas/Infrastructure/Persistence/Models/PromesaModel.php
- Promesas/Infrastructure/Persistence/Repositories/EloquentPromesaRepository.php
- Promesas/Infrastructure/Providers/PromesasServiceProvider.php
- Reportes/Infrastructure/Http/Livewire/DashboardOperativo.php
- Reportes/Infrastructure/Providers/ReportesServiceProvider.php
- Usuarios/Infrastructure/Persistence/Models/PermisoModel.php
- Usuarios/Infrastructure/Persistence/Models/RolModel.php
- Usuarios/Infrastructure/Providers/UsuariosServiceProvider.php
