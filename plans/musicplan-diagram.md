# MusicPlan System Architecture

## Entity Relationship Diagram

```mermaid
erDiagram
    users ||--o{ music_plans : creates
    music_plans {
        bigint id PK
        bigint user_id FK        
        string celebrationName
        %%  comment is optional
        string comment 
        date actual_date
        string setting
        integer season_id
        integer week_id
        integer day_id
        string readings_code
        char liturgical_year
        string parity
        boolean is_private
        timestamp created_at
        timestamp updated_at
    }
    
    users {
        bigint id PK
        string name
        string email
        timestamp email_verified_at
        string password
        string remember_token
        timestamp created_at
        timestamp updated_at
        bigint city_id FK
        bigint first_name_id FK
    }
```

## Lookup Workflow

```mermaid
flowchart TD
    A[Start with current MusicPlan] -->  E[Query database for matches]
    
    E --> F[Build OR conditions]
    F --> G[Condition 1: Same celebrationName]
    F --> H[Condition 2: Same season_id, week_id, day_id]
    F --> I[Condition 3: Same readings_code]
    
    G --> J[Combine with OR logic]
    H --> J
    I --> J
    
    J --> K[Add filters:<br/>is_private = false<br/>id != current_plan_id]
    
    K --> L[Execute query<br/>ORDER BY actual_date DESC]
    
    L --> M{Found matches?}
    M -->|Yes| N[Return suggested MusicPlans<br/>with match type indicated]
    M -->|No| O[Return empty suggestions]
    
    N --> P[Display to user<br/>Grouped by match type]
    O --> P
```

**Lookup Logic Details:**
- **Match Type 1 (Celebration)**: `celebrationName = ?`
- **Match Type 2 (Liturgical)**: `season_id = ? AND week_id = ? AND day_id = ?` (liturgical_year NOT considered)
- **Match Type 3 (Readings)**: `readings_code = ?`
- **Filters Applied**: `is_private = false`, `id != current_plan_id`
- **Ordering**: `actual_date DESC` (most recent first)
- **Scope**: All published plans regardless of user

## Model Relationships

```mermaid
classDiagram
    class User {
        +id: bigint
        +name: string
        +email: string
        +musicPlans() HasMany
        +city() BelongsTo
        +firstName() BelongsTo
    }
    
    class MusicPlan {
        +id: bigint
        +user_id: bigint
        +name: string
        +actual_date: Date
        +setting: MusicPlanSetting
        +season_id: integer
        +week_id: integer
        +day_id: integer
        +readings_code: string
        +liturgical_year: char
        +parity: string
        +is_private: boolean
        +user() BelongsTo
        +previousPlans() Collection
        +isFixedDateFeast() bool
    }
    
    class MusicPlanSetting {
        <<enumeration>>
        ORGANIST = "organist"
        GUITARIST = "guitarist"
        OTHER = "other"
        +label() string
        +icon() string
        +color() string
        +options() array
    }
    
    User "1" -- "*" MusicPlan : creates
    MusicPlan "1" -- "1" MusicPlanSetting : has
```

## Database Index Strategy

| Index | Columns | Purpose | Query Example |
|-------|---------|---------|---------------|
| Primary | `id` | Unique identification | `WHERE id = ?` |
| User Visibility | `user_id, is_private` | User-specific queries | `WHERE user_id = ? AND is_private = ?` |
| Liturgical Lookup | `season_id, week_id, day_id, liturgical_year` | Historical plan lookup | `WHERE season_id = ? AND week_id = ? AND day_id = ? AND liturgical_year = ?` |
| Date Lookup | `actual_date` | Date-based queries | `WHERE actual_date BETWEEN ? AND ?` |
| Setting Filter | `setting` | Filter by instrument type | `WHERE setting = ?` |
| **Celebration Lookup** | `celebrationName` | Celebration name matching | `WHERE celebrationName = ?` |
| **Readings Lookup** | `readings_code` | Readings code matching | `WHERE readings_code = ?` |

## Field Validation Rules

| Field | Type | Validation | Notes |
|-------|------|------------|-------|
| `name` | string | required, max:255 | Feast name |
| `actual_date` | date | required, date | Must be valid date |
| `setting` | string | required, in:organist,guitarist,other | Enum validation |
| `season_id` | integer | required, integer, min:0 | Liturgical season |
| `week_id` | integer | required, integer, min:1, max:52 | Week of season |
| `day_id` | integer | required, integer, min:1, max:7 | Day of week |
| `readings_code` | string | nullable, max:50 | External system code |
| `liturgical_year` | char | required, in:A,B,C | Liturgical cycle year |
| `parity` | string | nullable, in:I,II | Weekday mass parity |
| `is_private` | boolean | boolean | Default: false |

## API Endpoints (Future Consideration)

```
GET    /api/music-plans                    # List user's music plans
POST   /api/music-plans                    # Create new music plan
GET    /api/music-plans/{id}               # Get specific music plan
PUT    /api/music-plans/{id}               # Update music plan
DELETE /api/music-plans/{id}               # Delete music plan
GET    /api/music-plans/{id}/previous      # Get previous similar plans
GET    /api/music-plans/filter/{setting}   # Filter by setting
POST   /api/music-plans/{id}/publish       # Publish a plan
POST   /api/music-plans/{id}/unpublish     # Unpublish a plan