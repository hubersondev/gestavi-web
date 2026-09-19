--
-- PostgreSQL database dump
--

-- Dumped from database version 17.2
-- Dumped by pg_dump version 17.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: account_transfers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.account_transfers (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    from_account_id bigint NOT NULL,
    to_account_id bigint NOT NULL,
    user_id bigint,
    reference character varying(50),
    amount numeric(15,2) NOT NULL,
    fees numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    transfer_date date NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    description text,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT account_transfers_amount_positive_check CHECK (((amount > (0)::numeric) AND (fees >= (0)::numeric))),
    CONSTRAINT account_transfers_different_accounts_check CHECK ((from_account_id <> to_account_id)),
    CONSTRAINT account_transfers_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: account_transfers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.account_transfers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: account_transfers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.account_transfers_id_seq OWNED BY public.account_transfers.id;


--
-- Name: accounts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.accounts (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    name character varying(255) NOT NULL,
    code character varying(50),
    type character varying(20) NOT NULL,
    bank_name character varying(255),
    account_number character varying(50),
    phone_number character varying(20),
    operator character varying(20),
    initial_balance numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    current_balance numeric(15,2) DEFAULT '0'::numeric NOT NULL,
    currency character varying(3) DEFAULT 'XOF'::character varying NOT NULL,
    description text,
    notes text,
    is_default boolean DEFAULT false NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT accounts_operator_check CHECK (((operator IS NULL) OR ((operator)::text = ANY ((ARRAY['orange'::character varying, 'mtn'::character varying, 'moov'::character varying, 'wave'::character varying])::text[])))),
    CONSTRAINT accounts_type_check CHECK (((type)::text = ANY ((ARRAY['cash'::character varying, 'bank'::character varying, 'mobile_money'::character varying, 'other'::character varying])::text[])))
);


--
-- Name: accounts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: accounts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.accounts_id_seq OWNED BY public.accounts.id;


--
-- Name: activity_log; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.activity_log (
    id bigint NOT NULL,
    log_name character varying(255),
    description text NOT NULL,
    subject_type character varying(255),
    subject_id bigint,
    causer_type character varying(255),
    causer_id bigint,
    properties json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    event character varying(255),
    batch_uuid uuid
);


--
-- Name: activity_log_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.activity_log_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: activity_log_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.activity_log_id_seq OWNED BY public.activity_log.id;


--
-- Name: alert_dispatches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.alert_dispatches (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    alert_key character varying(60) NOT NULL,
    dispatched_on date NOT NULL,
    items_count integer DEFAULT 0 NOT NULL,
    recipients_count integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN alert_dispatches.alert_key; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.alert_dispatches.alert_key IS 'overdue_vaccinations, missing_daily_records, overdue_invoices, low_stock…';


--
-- Name: alert_dispatches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.alert_dispatches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: alert_dispatches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.alert_dispatches_id_seq OWNED BY public.alert_dispatches.id;


--
-- Name: attendances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.attendances (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    recorded_by bigint,
    attendance_date date NOT NULL,
    status character varying(20) DEFAULT 'present'::character varying NOT NULL,
    check_in time(0) without time zone,
    check_out time(0) without time zone,
    late_minutes smallint DEFAULT '0'::smallint NOT NULL,
    overtime_hours numeric(4,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT attendances_status_check CHECK (((status)::text = ANY ((ARRAY['present'::character varying, 'absent'::character varying, 'late'::character varying, 'on_leave'::character varying, 'holiday'::character varying])::text[])))
);


--
-- Name: attendances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.attendances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: attendances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.attendances_id_seq OWNED BY public.attendances.id;


--
-- Name: buildings; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.buildings (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    type character varying(50),
    capacity integer,
    area_sqm numeric(8,2),
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: buildings_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.buildings_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: buildings_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.buildings_id_seq OWNED BY public.buildings.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration integer NOT NULL
);


--
-- Name: chicken_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.chicken_types (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    production_kind character varying(20) DEFAULT 'mixed'::character varying NOT NULL,
    CONSTRAINT chicken_types_production_kind_check CHECK (((production_kind)::text = ANY ((ARRAY['broiler'::character varying, 'layer'::character varying, 'breeder'::character varying, 'mixed'::character varying])::text[])))
);


--
-- Name: chicken_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.chicken_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: chicken_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.chicken_types_id_seq OWNED BY public.chicken_types.id;


--
-- Name: cities; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cities (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    country_id bigint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: cities_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.cities_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: cities_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.cities_id_seq OWNED BY public.cities.id;


--
-- Name: contract_amendments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contract_amendments (
    id bigint NOT NULL,
    contract_id bigint NOT NULL,
    amendment_number character varying(255) NOT NULL,
    effective_date date NOT NULL,
    reason character varying(255) NOT NULL,
    previous_values json NOT NULL,
    new_values json NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: contract_amendments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contract_amendments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contract_amendments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contract_amendments_id_seq OWNED BY public.contract_amendments.id;


--
-- Name: contracts; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.contracts (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    renewed_by_contract_id bigint,
    contract_number character varying(50),
    type character varying(20) NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    start_date date NOT NULL,
    end_date date,
    base_salary numeric(12,2) NOT NULL,
    transport_allowance numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    housing_allowance numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    food_allowance numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    working_hours_per_week smallint DEFAULT '40'::smallint NOT NULL,
    annual_leave_days smallint DEFAULT '24'::smallint NOT NULL,
    "position" character varying(255),
    work_location character varying(255),
    description text,
    termination_reason text,
    termination_date date,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT contracts_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'active'::character varying, 'expired'::character varying, 'renewed'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT contracts_type_check CHECK (((type)::text = ANY ((ARRAY['cdi'::character varying, 'cdd'::character varying, 'seasonal'::character varying, 'intern'::character varying, 'freelance'::character varying])::text[])))
);


--
-- Name: contracts_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.contracts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: contracts_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.contracts_id_seq OWNED BY public.contracts.id;


--
-- Name: countries; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.countries (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(2) NOT NULL,
    code_iso3 character varying(3),
    phone_code character varying(10),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    currency_id bigint,
    default_language character varying(5)
);


--
-- Name: COLUMN countries.code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.countries.code IS 'Code ISO 3166-1 alpha-2';


--
-- Name: COLUMN countries.code_iso3; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.countries.code_iso3 IS 'Code ISO 3166-1 alpha-3';


--
-- Name: COLUMN countries.phone_code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.countries.phone_code IS 'Indicatif téléphonique';


--
-- Name: COLUMN countries.default_language; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.countries.default_language IS 'Code langue ISO 639-1 (fr, en, pt...)';


--
-- Name: countries_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.countries_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: countries_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.countries_id_seq OWNED BY public.countries.id;


--
-- Name: credit_notes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.credit_notes (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    sale_id bigint NOT NULL,
    customer_id bigint,
    user_id bigint,
    credit_note_number character varying(50) NOT NULL,
    issue_date date NOT NULL,
    amount numeric(12,2) NOT NULL,
    reason character varying(255),
    description text,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    applied_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    applied_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    refunded_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    refund_account_id bigint,
    CONSTRAINT credit_notes_amount_positive_check CHECK ((amount > (0)::numeric)),
    CONSTRAINT credit_notes_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'issued'::character varying, 'applied'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: COLUMN credit_notes.applied_amount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.credit_notes.applied_amount IS 'Part imputée en réduction du solde dû de la facture';


--
-- Name: COLUMN credit_notes.refunded_amount; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.credit_notes.refunded_amount IS 'Part remboursée au client (débit de trésorerie)';


--
-- Name: credit_notes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.credit_notes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: credit_notes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.credit_notes_id_seq OWNED BY public.credit_notes.id;


--
-- Name: currencies; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.currencies (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    code character varying(3) NOT NULL,
    symbol character varying(10) NOT NULL,
    decimal_places smallint DEFAULT '2'::smallint NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN currencies.name; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.currencies.name IS 'Nom complet de la devise';


--
-- Name: COLUMN currencies.code; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.currencies.code IS 'Code ISO 4217 (XOF, EUR, USD...)';


--
-- Name: COLUMN currencies.symbol; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.currencies.symbol IS 'Symbole affiché (FCFA, €, $...)';


--
-- Name: COLUMN currencies.decimal_places; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.currencies.decimal_places IS 'Nombre de décimales';


--
-- Name: currencies_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.currencies_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: currencies_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.currencies_id_seq OWNED BY public.currencies.id;


--
-- Name: customers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.customers (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    name character varying(255) NOT NULL,
    code character varying(30),
    type character varying(20) NOT NULL,
    phone character varying(30),
    phone_secondary character varying(30),
    email character varying(255),
    address text,
    city_id bigint,
    credit_limit numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT customers_type_check CHECK (((type)::text = ANY ((ARRAY['particulier'::character varying, 'revendeur'::character varying, 'restaurant'::character varying, 'hotel'::character varying, 'supermarche'::character varying, 'autre'::character varying])::text[])))
);


--
-- Name: customers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.customers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: customers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.customers_id_seq OWNED BY public.customers.id;


--
-- Name: daily_records; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.daily_records (
    id bigint NOT NULL,
    flock_id bigint NOT NULL,
    recorded_by bigint,
    record_date date NOT NULL,
    age_days integer DEFAULT 0 NOT NULL,
    mortality integer DEFAULT 0 NOT NULL,
    mortality_cause character varying(255),
    eggs_collected integer DEFAULT 0 NOT NULL,
    eggs_broken integer DEFAULT 0 NOT NULL,
    eggs_sold integer DEFAULT 0 NOT NULL,
    feed_consumed_kg numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    water_consumed_l numeric(8,2) DEFAULT '0'::numeric NOT NULL,
    average_weight_g numeric(8,2),
    birds_weighed integer,
    health_observations text,
    treatments text,
    temperature_min numeric(4,1),
    temperature_max numeric(4,1),
    humidity_percent numeric(4,1),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    culls integer DEFAULT 0 NOT NULL,
    birds_present integer,
    correction_reason text
);


--
-- Name: COLUMN daily_records.culls; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daily_records.culls IS 'Sujets réformés / triés (sortis vivants du lot, hors mortalité)';


--
-- Name: COLUMN daily_records.birds_present; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daily_records.birds_present IS 'Effectif présent en fin de journée, base des taux journaliers';


--
-- Name: COLUMN daily_records.correction_reason; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.daily_records.correction_reason IS 'Motif obligatoire de toute correction rétroactive';


--
-- Name: daily_records_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.daily_records_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: daily_records_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.daily_records_id_seq OWNED BY public.daily_records.id;


--
-- Name: employees; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.employees (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    user_id bigint,
    employee_number character varying(50) NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    gender character varying(10),
    birth_date date,
    birth_place character varying(255),
    nationality character varying(255) DEFAULT 'Ivoirienne'::character varying NOT NULL,
    marital_status character varying(20),
    cnps_number character varying(100),
    phone character varying(255),
    phone_secondary character varying(255),
    email character varying(255),
    address text,
    city character varying(255),
    emergency_contact_name character varying(255),
    emergency_contact_phone character varying(255),
    emergency_contact_relationship character varying(255),
    department character varying(255),
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    hire_date date NOT NULL,
    termination_date date,
    termination_reason text,
    bank_name character varying(255),
    bank_account character varying(255),
    mobile_money_number character varying(255),
    mobile_money_operator character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    id_type character varying(30),
    id_number character varying(100),
    id_expiry_date date,
    photo character varying(255),
    is_resident boolean DEFAULT false NOT NULL,
    is_on_site boolean DEFAULT false NOT NULL,
    accommodation character varying(255),
    residence_start_date date,
    requires_patrol boolean DEFAULT false NOT NULL,
    patrol_interval_hours smallint,
    id_document character varying(255),
    payment_method character varying(20),
    fiscal_parts numeric(3,1) DEFAULT '1'::numeric NOT NULL,
    CONSTRAINT employees_fiscal_parts_check CHECK (((fiscal_parts >= (1)::numeric) AND (fiscal_parts <= (5)::numeric) AND ((fiscal_parts * (2)::numeric) = floor((fiscal_parts * (2)::numeric))))),
    CONSTRAINT employees_gender_check CHECK (((gender)::text = ANY ((ARRAY['male'::character varying, 'female'::character varying, 'other'::character varying])::text[]))),
    CONSTRAINT employees_id_type_check CHECK (((id_type)::text = ANY ((ARRAY['cni'::character varying, 'passport'::character varying, 'attestation'::character varying, 'carte_consulaire'::character varying])::text[]))),
    CONSTRAINT employees_payment_method_check CHECK (((payment_method)::text = ANY ((ARRAY['cash'::character varying, 'bank_transfer'::character varying, 'mobile_money'::character varying])::text[]))),
    CONSTRAINT employees_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'on_leave'::character varying, 'suspended'::character varying, 'terminated'::character varying])::text[])))
);


--
-- Name: employees_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.employees_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: employees_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.employees_id_seq OWNED BY public.employees.id;


--
-- Name: equipment_maintenances; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_maintenances (
    id bigint NOT NULL,
    equipment_id bigint NOT NULL,
    performed_by bigint,
    maintenance_type character varying(30) NOT NULL,
    title character varying(255) NOT NULL,
    description text,
    labor_cost numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    parts_cost numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    total_cost numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    external_technician character varying(255),
    external_company character varying(255),
    scheduled_date date,
    performed_date date,
    next_maintenance_date date,
    condition_before character varying(30),
    condition_after character varying(30),
    status character varying(30) DEFAULT 'scheduled'::character varying NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT equipment_maintenances_status_check CHECK (((status)::text = ANY ((ARRAY['scheduled'::character varying, 'in_progress'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT equipment_maintenances_type_check CHECK (((maintenance_type)::text = ANY ((ARRAY['preventive'::character varying, 'corrective'::character varying, 'inspection'::character varying, 'cleaning'::character varying, 'calibration'::character varying, 'replacement'::character varying, 'emergency'::character varying])::text[])))
);


--
-- Name: equipment_maintenances_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipment_maintenances_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_maintenances_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_maintenances_id_seq OWNED BY public.equipment_maintenances.id;


--
-- Name: equipment_movements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipment_movements (
    id bigint NOT NULL,
    equipment_id bigint NOT NULL,
    moved_by bigint,
    from_building_id bigint,
    to_building_id bigint,
    movement_type character varying(30) NOT NULL,
    movement_date timestamp(0) without time zone NOT NULL,
    reason text,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT equipment_movements_type_check CHECK (((movement_type)::text = ANY ((ARRAY['transfer'::character varying, 'entry'::character varying, 'exit'::character varying, 'loan'::character varying, 'return'::character varying, 'installation'::character varying, 'decommission'::character varying])::text[])))
);


--
-- Name: equipment_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipment_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipment_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipment_movements_id_seq OWNED BY public.equipment_movements.id;


--
-- Name: equipments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.equipments (
    id bigint NOT NULL,
    building_id bigint,
    inventory_number character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    category character varying(50) NOT NULL,
    brand character varying(255),
    condition character varying(50),
    purchase_date date,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    supplier_id bigint,
    serial_number character varying(255),
    model character varying(255),
    capacity integer,
    capacity_unit character varying(30),
    purchase_price numeric(12,2),
    warranty_expiry_date date,
    current_value numeric(12,2),
    lifespan_years smallint,
    purchase_reference character varying(255),
    status character varying(30) DEFAULT 'in_service'::character varying NOT NULL,
    last_maintenance_date date,
    next_maintenance_date date,
    maintenance_interval_days smallint,
    notes text,
    farm_id bigint,
    CONSTRAINT equipments_condition_check CHECK (((condition IS NULL) OR ((condition)::text = ANY ((ARRAY['new'::character varying, 'good'::character varying, 'fair'::character varying, 'poor'::character varying, 'broken'::character varying, 'damaged'::character varying])::text[])))),
    CONSTRAINT equipments_status_check CHECK (((status)::text = ANY ((ARRAY['in_service'::character varying, 'in_stock'::character varying, 'in_maintenance'::character varying, 'out_of_service'::character varying, 'disposed'::character varying, 'under_maintenance'::character varying, 'retired'::character varying])::text[])))
);


--
-- Name: equipments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.equipments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: equipments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.equipments_id_seq OWNED BY public.equipments.id;


--
-- Name: expenses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.expenses (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    flock_id bigint,
    supplier_id bigint,
    account_id bigint,
    user_id bigint,
    expense_number character varying(50) NOT NULL,
    expense_date date NOT NULL,
    due_date date,
    paid_at timestamp(0) without time zone,
    category character varying(30) NOT NULL,
    status character varying(20) DEFAULT 'unpaid'::character varying NOT NULL,
    amount numeric(12,2) NOT NULL,
    paid_amount numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    description character varying(255) NOT NULL,
    reference character varying(255),
    attachment character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT expenses_amount_positive_check CHECK (((amount > (0)::numeric) AND (paid_amount >= (0)::numeric))),
    CONSTRAINT expenses_category_check CHECK (((category)::text = ANY ((ARRAY['feed'::character varying, 'medicine'::character varying, 'veterinary'::character varying, 'fuel'::character varying, 'utilities'::character varying, 'rent'::character varying, 'maintenance'::character varying, 'equipment'::character varying, 'transport'::character varying, 'services'::character varying, 'taxes'::character varying, 'salaries'::character varying, 'other'::character varying])::text[]))),
    CONSTRAINT expenses_status_check CHECK (((status)::text = ANY ((ARRAY['unpaid'::character varying, 'partial'::character varying, 'paid'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: expenses_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.expenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: expenses_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.expenses_id_seq OWNED BY public.expenses.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: farm_logs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.farm_logs (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    flock_id bigint,
    building_id bigint,
    recorded_by bigint NOT NULL,
    resolved_by bigint,
    log_date date NOT NULL,
    log_time time(0) without time zone,
    title character varying(255) NOT NULL,
    category character varying(255) DEFAULT 'observation'::character varying NOT NULL,
    priority character varying(255) DEFAULT 'normal'::character varying NOT NULL,
    description text,
    actions_taken text,
    attachments json,
    weather character varying(255),
    temperature numeric(4,1),
    humidity numeric(4,1),
    requires_follow_up boolean DEFAULT false NOT NULL,
    follow_up_date date,
    is_resolved boolean DEFAULT false NOT NULL,
    resolved_at timestamp(0) without time zone,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: farm_logs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.farm_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: farm_logs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.farm_logs_id_seq OWNED BY public.farm_logs.id;


--
-- Name: farm_organisation_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.farm_organisation_user (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    organisation_user_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: farm_organisation_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.farm_organisation_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: farm_organisation_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.farm_organisation_user_id_seq OWNED BY public.farm_organisation_user.id;


--
-- Name: farms; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.farms (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    code character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    capacity integer DEFAULT 0 NOT NULL,
    address text,
    city_id bigint,
    latitude numeric(10,7),
    longitude numeric(10,7),
    phone character varying(20),
    email character varying(255),
    chicken_type_id bigint,
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: farms_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.farms_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: farms_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.farms_id_seq OWNED BY public.farms.id;


--
-- Name: flocks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.flocks (
    id bigint NOT NULL,
    building_id bigint NOT NULL,
    chicken_type_id bigint,
    code character varying(50) NOT NULL,
    name character varying(255),
    quantity_started integer NOT NULL,
    quantity_current integer DEFAULT 0 NOT NULL,
    age_at_arrival smallint DEFAULT '0'::smallint NOT NULL,
    start_date date NOT NULL,
    expected_end_date date,
    actual_end_date date,
    status character varying(20) DEFAULT 'planned'::character varying NOT NULL,
    purchase_price_per_unit numeric(12,2),
    total_purchase_price numeric(14,2),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    supplier_id bigint,
    breed character varying(255),
    quantity_sold integer DEFAULT 0 NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT flocks_quantity_check CHECK ((quantity_current <= quantity_started)),
    CONSTRAINT flocks_status_check CHECK (((status)::text = ANY ((ARRAY['planned'::character varying, 'active'::character varying, 'completed'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: COLUMN flocks.quantity_sold; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.flocks.quantity_sold IS 'Sujets vivants vendus (cumul des lignes de vente is_live_birds)';


--
-- Name: flocks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.flocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: flocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.flocks_id_seq OWNED BY public.flocks.id;


--
-- Name: health_incidents; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.health_incidents (
    id bigint NOT NULL,
    flock_id bigint NOT NULL,
    reported_by bigint,
    title character varying(255) NOT NULL,
    incident_date date NOT NULL,
    detected_date date,
    severity character varying(255) NOT NULL,
    category character varying(255) NOT NULL,
    symptoms text NOT NULL,
    suspected_disease character varying(255),
    confirmed_disease character varying(255),
    birds_affected integer NOT NULL,
    birds_dead integer DEFAULT 0 NOT NULL,
    treatment_applied text,
    treatment_start_date date,
    treatment_end_date date,
    treatment_cost numeric(10,2),
    quarantine_applied boolean DEFAULT false NOT NULL,
    quarantine_start_date date,
    quarantine_end_date date,
    status character varying(255) DEFAULT 'detected'::character varying NOT NULL,
    resolution_date date,
    resolution_notes text,
    lessons_learned text,
    preventive_measures text,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: health_incidents_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.health_incidents_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: health_incidents_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.health_incidents_id_seq OWNED BY public.health_incidents.id;


--
-- Name: health_programs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.health_programs (
    id bigint NOT NULL,
    organisation_id bigint,
    farm_id bigint,
    treatment_name character varying(255) NOT NULL,
    treatment_type character varying(30) NOT NULL,
    scheduled_day smallint NOT NULL,
    administration_route character varying(30),
    dosage character varying(100),
    withdrawal_days smallint DEFAULT '0'::smallint NOT NULL,
    is_mandatory boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    chicken_type_id bigint
);


--
-- Name: health_programs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.health_programs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: health_programs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.health_programs_id_seq OWNED BY public.health_programs.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: leaves; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.leaves (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    approved_by bigint,
    type character varying(30) NOT NULL,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    start_date date NOT NULL,
    end_date date NOT NULL,
    days_count smallint DEFAULT '1'::smallint NOT NULL,
    reason text,
    rejection_reason text,
    approved_at timestamp(0) without time zone,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    attachment character varying(255),
    CONSTRAINT leaves_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'approved'::character varying, 'rejected'::character varying, 'cancelled'::character varying])::text[]))),
    CONSTRAINT leaves_type_check CHECK (((type)::text = ANY ((ARRAY['annual'::character varying, 'sick'::character varying, 'maternity'::character varying, 'paternity'::character varying, 'unpaid'::character varying, 'exceptional'::character varying])::text[])))
);


--
-- Name: leaves_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.leaves_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: leaves_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.leaves_id_seq OWNED BY public.leaves.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: notifications; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.notifications (
    id uuid NOT NULL,
    type character varying(255) NOT NULL,
    notifiable_type character varying(255) NOT NULL,
    notifiable_id bigint NOT NULL,
    data jsonb NOT NULL,
    read_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: organisation_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organisation_permissions (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    slug character varying(80) NOT NULL,
    "group" character varying(50) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: organisation_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organisation_permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organisation_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organisation_permissions_id_seq OWNED BY public.organisation_permissions.id;


--
-- Name: organisation_role_permission; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organisation_role_permission (
    organisation_role_id bigint NOT NULL,
    organisation_permission_id bigint NOT NULL
);


--
-- Name: organisation_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organisation_roles (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    slug character varying(80) NOT NULL,
    weight smallint DEFAULT '1'::smallint NOT NULL,
    is_system boolean DEFAULT false NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: organisation_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organisation_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organisation_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organisation_roles_id_seq OWNED BY public.organisation_roles.id;


--
-- Name: organisation_users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organisation_users (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    user_id bigint NOT NULL,
    status character varying(20) DEFAULT 'active'::character varying NOT NULL,
    permissions jsonb,
    joined_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    organisation_role_id bigint NOT NULL,
    CONSTRAINT organisation_users_status_check CHECK (((status)::text = ANY ((ARRAY['active'::character varying, 'invited'::character varying, 'suspended'::character varying])::text[])))
);


--
-- Name: organisation_users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organisation_users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organisation_users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organisation_users_id_seq OWNED BY public.organisation_users.id;


--
-- Name: organisations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.organisations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    email character varying(255),
    phone character varying(20),
    address text,
    logo_url character varying(255),
    description text,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    country_id bigint,
    city_id bigint,
    plan_id bigint,
    deleted_at timestamp(0) without time zone
);


--
-- Name: organisations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.organisations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: organisations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.organisations_id_seq OWNED BY public.organisations.id;


--
-- Name: password_reset_tokens; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.password_reset_tokens (
    email character varying(255) NOT NULL,
    token character varying(255) NOT NULL,
    created_at timestamp(0) without time zone
);


--
-- Name: payments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payments (
    id bigint NOT NULL,
    sale_id bigint NOT NULL,
    account_id bigint,
    user_id bigint,
    amount numeric(12,2) NOT NULL,
    method character varying(20) NOT NULL,
    reference character varying(255),
    payment_date date NOT NULL,
    is_recorded boolean DEFAULT false NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payments_amount_positive_check CHECK ((amount > (0)::numeric)),
    CONSTRAINT payments_method_check CHECK (((method)::text = ANY ((ARRAY['cash'::character varying, 'orange_money'::character varying, 'mtn_momo'::character varying, 'moov_money'::character varying, 'wave'::character varying, 'bank_transfer'::character varying, 'cheque'::character varying, 'credit'::character varying])::text[])))
);


--
-- Name: payments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;


--
-- Name: payroll_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_items (
    id bigint NOT NULL,
    payroll_id bigint NOT NULL,
    type character varying(30) NOT NULL,
    label character varying(255) NOT NULL,
    amount numeric(12,2) NOT NULL,
    is_deduction boolean DEFAULT false NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payroll_items_type_check CHECK (((type)::text = ANY ((ARRAY['base_salary'::character varying, 'bonus'::character varying, 'allowance'::character varying, 'overtime'::character varying, 'deduction'::character varying, 'cnps_employee'::character varying, 'cnps_employer'::character varying, 'its'::character varying])::text[])))
);


--
-- Name: payroll_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_items_id_seq OWNED BY public.payroll_items.id;


--
-- Name: payroll_parameters; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_parameters (
    id bigint NOT NULL,
    organisation_id bigint,
    effective_from date NOT NULL,
    cnps_ceiling numeric(15,2) NOT NULL,
    cnps_employee_rate numeric(6,4) NOT NULL,
    cnps_employer_pension_rate numeric(6,4) NOT NULL,
    cnps_family_rate numeric(6,4) DEFAULT '0'::numeric NOT NULL,
    cnps_work_accident_rate numeric(6,4) DEFAULT '0'::numeric NOT NULL,
    cnps_family_ceiling numeric(15,2),
    its_abatement_rate numeric(5,4) DEFAULT '0'::numeric NOT NULL,
    family_reductions json,
    source character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: payroll_parameters_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_parameters_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_parameters_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_parameters_id_seq OWNED BY public.payroll_parameters.id;


--
-- Name: payroll_tax_brackets; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payroll_tax_brackets (
    id bigint NOT NULL,
    payroll_parameter_id bigint NOT NULL,
    lower_bound numeric(15,2) NOT NULL,
    upper_bound numeric(15,2),
    rate numeric(5,4) NOT NULL,
    "order" smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT payroll_tax_brackets_bounds_check CHECK (((upper_bound IS NULL) OR (upper_bound > lower_bound))),
    CONSTRAINT payroll_tax_brackets_rate_check CHECK (((rate >= (0)::numeric) AND (rate <= (1)::numeric)))
);


--
-- Name: payroll_tax_brackets_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payroll_tax_brackets_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payroll_tax_brackets_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payroll_tax_brackets_id_seq OWNED BY public.payroll_tax_brackets.id;


--
-- Name: payrolls; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.payrolls (
    id bigint NOT NULL,
    employee_id bigint NOT NULL,
    contract_id bigint,
    validated_by bigint,
    paid_by bigint,
    period_year smallint NOT NULL,
    period_month smallint NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    gross_salary numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    cnps_employee numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    cnps_employer numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    its numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    other_deductions numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total_deductions numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    net_salary numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    worked_days smallint,
    leave_days smallint DEFAULT '0'::smallint NOT NULL,
    overtime_hours numeric(6,2) DEFAULT '0'::numeric NOT NULL,
    validated_at timestamp(0) without time zone,
    paid_at date,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    payroll_parameter_id bigint,
    expense_id bigint,
    cnps_ceiling_applied numeric(15,2),
    fiscal_parts_applied numeric(3,1),
    deleted_at timestamp(0) without time zone,
    CONSTRAINT payrolls_period_month_check CHECK (((period_month >= 1) AND (period_month <= 12))),
    CONSTRAINT payrolls_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'validated'::character varying, 'paid'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: payrolls_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.payrolls_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: payrolls_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.payrolls_id_seq OWNED BY public.payrolls.id;


--
-- Name: plans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.plans (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    description text,
    monthly_price numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    yearly_price numeric(10,2),
    duration_months integer DEFAULT 1 NOT NULL,
    max_farms integer,
    max_users integer,
    trial_days integer DEFAULT 0 NOT NULL,
    features json,
    sort_order integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN plans.max_farms; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.plans.max_farms IS 'null = illimité';


--
-- Name: COLUMN plans.max_users; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.plans.max_users IS 'null = illimité';


--
-- Name: plans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: plans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.plans_id_seq OWNED BY public.plans.id;


--
-- Name: platform_permissions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_permissions (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    slug character varying(100) NOT NULL,
    "group" character varying(50) NOT NULL,
    description character varying(255),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: platform_permissions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_permissions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_permissions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_permissions_id_seq OWNED BY public.platform_permissions.id;


--
-- Name: platform_role_permission; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_role_permission (
    id bigint NOT NULL,
    platform_role_id bigint NOT NULL,
    platform_permission_id bigint NOT NULL
);


--
-- Name: platform_role_permission_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_role_permission_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_role_permission_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_role_permission_id_seq OWNED BY public.platform_role_permission.id;


--
-- Name: platform_role_user; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_role_user (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    platform_role_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: platform_role_user_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_role_user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_role_user_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_role_user_id_seq OWNED BY public.platform_role_user.id;


--
-- Name: platform_roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.platform_roles (
    id bigint NOT NULL,
    name character varying(50) NOT NULL,
    slug character varying(50) NOT NULL,
    description character varying(255),
    is_system boolean DEFAULT false NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: platform_roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.platform_roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: platform_roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.platform_roles_id_seq OWNED BY public.platform_roles.id;


--
-- Name: production_phases; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.production_phases (
    id bigint NOT NULL,
    organisation_id bigint,
    chicken_type_id bigint NOT NULL,
    name character varying(100) NOT NULL,
    code character varying(50),
    start_day integer NOT NULL,
    end_day integer NOT NULL,
    min_temperature numeric(4,1),
    max_temperature numeric(4,1),
    humidity_min numeric(4,1),
    humidity_max numeric(4,1),
    light_hours numeric(4,1),
    density_per_m2 numeric(5,2),
    feed_type text,
    description text,
    "order" integer DEFAULT 0 NOT NULL,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: production_phases_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.production_phases_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: production_phases_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.production_phases_id_seq OWNED BY public.production_phases.id;


--
-- Name: purchase_order_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_order_items (
    id bigint NOT NULL,
    purchase_order_id bigint NOT NULL,
    stock_id bigint,
    stock_lot_id bigint,
    product_name character varying(255) NOT NULL,
    product_code character varying(50),
    description text,
    unit character varying(30) NOT NULL,
    quantity_ordered numeric(12,2) NOT NULL,
    quantity_received numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    unit_price numeric(12,2) NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tax_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) NOT NULL,
    expected_date date,
    status character varying(20) DEFAULT 'pending'::character varying NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT purchase_order_items_qty_check CHECK (((quantity_ordered > (0)::numeric) AND (quantity_received >= (0)::numeric) AND (quantity_received <= quantity_ordered))),
    CONSTRAINT purchase_order_items_status_check CHECK (((status)::text = ANY ((ARRAY['pending'::character varying, 'partial'::character varying, 'received'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_order_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_order_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_order_items_id_seq OWNED BY public.purchase_order_items.id;


--
-- Name: purchase_orders; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.purchase_orders (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    supplier_id bigint NOT NULL,
    created_by bigint,
    approved_by bigint,
    order_number character varying(50) NOT NULL,
    order_date date NOT NULL,
    expected_delivery_date date,
    actual_delivery_date date,
    status character varying(30) DEFAULT 'draft'::character varying NOT NULL,
    subtotal numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    shipping_cost numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    payment_terms character varying(20) DEFAULT 'on_delivery'::character varying NOT NULL,
    payment_terms_details text,
    delivery_address text,
    delivery_contact character varying(255),
    delivery_phone character varying(30),
    supplier_quote_ref character varying(255),
    supplier_order_ref character varying(255),
    notes text,
    internal_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT purchase_orders_payment_terms_check CHECK (((payment_terms)::text = ANY ((ARRAY['immediate'::character varying, 'on_delivery'::character varying, 'net_7'::character varying, 'net_15'::character varying, 'net_30'::character varying, 'net_60'::character varying, 'custom'::character varying])::text[]))),
    CONSTRAINT purchase_orders_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending_approval'::character varying, 'approved'::character varying, 'sent'::character varying, 'partially_received'::character varying, 'received'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.purchase_orders_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: purchase_orders_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.purchase_orders_id_seq OWNED BY public.purchase_orders.id;


--
-- Name: quote_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quote_items (
    id bigint NOT NULL,
    quote_id bigint NOT NULL,
    flock_id bigint,
    product_name character varying(255) NOT NULL,
    description text,
    unit character varying(30) DEFAULT 'unité'::character varying NOT NULL,
    quantity numeric(10,2) NOT NULL,
    unit_price numeric(12,2) NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: quote_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.quote_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quote_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quote_items_id_seq OWNED BY public.quote_items.id;


--
-- Name: quotes; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.quotes (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    customer_id bigint,
    user_id bigint,
    converted_sale_id bigint,
    quote_number character varying(50) NOT NULL,
    quote_date date NOT NULL,
    valid_until date,
    reference character varying(255),
    client_name character varying(255),
    client_phone character varying(30),
    client_email character varying(255),
    client_address text,
    subtotal numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tax_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    terms_conditions text,
    notes text,
    internal_notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT quotes_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'sent'::character varying, 'accepted'::character varying, 'rejected'::character varying, 'expired'::character varying, 'converted'::character varying])::text[])))
);


--
-- Name: quotes_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.quotes_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: quotes_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.quotes_id_seq OWNED BY public.quotes.id;


--
-- Name: sale_items; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sale_items (
    id bigint NOT NULL,
    sale_id bigint NOT NULL,
    flock_id bigint,
    product_name character varying(255) NOT NULL,
    description text,
    unit character varying(30) DEFAULT 'unité'::character varying NOT NULL,
    quantity numeric(10,2) NOT NULL,
    unit_price numeric(12,2) NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    sort_order smallint DEFAULT '0'::smallint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_live_birds boolean DEFAULT false NOT NULL
);


--
-- Name: COLUMN sale_items.is_live_birds; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sale_items.is_live_birds IS 'Vente de sujets vivants : décrémente l''effectif de la bande liée';


--
-- Name: sale_items_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sale_items_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sale_items_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sale_items_id_seq OWNED BY public.sale_items.id;


--
-- Name: sales; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sales (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    customer_id bigint,
    user_id bigint,
    quote_id bigint,
    account_id bigint,
    invoice_number character varying(50) NOT NULL,
    sale_date date NOT NULL,
    due_date date,
    subtotal numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    discount_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    discount_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    tax_percent numeric(5,2) DEFAULT '0'::numeric NOT NULL,
    tax_amount numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    total numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    paid numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    balance numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    status character varying(20) DEFAULT 'draft'::character varying NOT NULL,
    payment_method character varying(20),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT sales_payment_method_check CHECK (((payment_method IS NULL) OR ((payment_method)::text = ANY ((ARRAY['cash'::character varying, 'orange_money'::character varying, 'mtn_momo'::character varying, 'moov_money'::character varying, 'wave'::character varying, 'bank_transfer'::character varying, 'cheque'::character varying, 'credit'::character varying])::text[])))),
    CONSTRAINT sales_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending'::character varying, 'partial'::character varying, 'paid'::character varying, 'cancelled'::character varying])::text[])))
);


--
-- Name: sales_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sales_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sales_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sales_id_seq OWNED BY public.sales.id;


--
-- Name: sequences; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sequences (
    id bigint NOT NULL,
    scope character varying(30) NOT NULL,
    scope_id bigint NOT NULL,
    key character varying(40) NOT NULL,
    year smallint,
    last_value integer DEFAULT 0 NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: COLUMN sequences.scope; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sequences.scope IS 'organisation | farm | employee | contract';


--
-- Name: COLUMN sequences.key; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sequences.key IS 'invoice, quote, expense, customer…';


--
-- Name: COLUMN sequences.year; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.sequences.year IS 'NULL pour les compteurs sans remise à zéro annuelle';


--
-- Name: sequences_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.sequences_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: sequences_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.sequences_id_seq OWNED BY public.sequences.id;


--
-- Name: sessions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL
);


--
-- Name: stock_categories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_categories (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    slug character varying(255) NOT NULL,
    type character varying(20) NOT NULL,
    description text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT stock_categories_type_check CHECK (((type)::text = ANY ((ARRAY['feed'::character varying, 'medicine'::character varying, 'material'::character varying, 'packaging'::character varying, 'other'::character varying])::text[])))
);


--
-- Name: stock_categories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_categories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_categories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_categories_id_seq OWNED BY public.stock_categories.id;


--
-- Name: stock_inventories; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_inventories (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    stock_id bigint NOT NULL,
    stock_lot_id bigint,
    conducted_by bigint,
    validated_by bigint,
    adjustment_movement_id bigint,
    inventory_ref character varying(50) NOT NULL,
    inventory_date date NOT NULL,
    theoretical_quantity numeric(12,2) NOT NULL,
    physical_quantity numeric(12,2) NOT NULL,
    difference numeric(12,2) NOT NULL,
    difference_percent numeric(8,2),
    unit_price numeric(12,2),
    difference_value numeric(12,2),
    status character varying(30) DEFAULT 'draft'::character varying NOT NULL,
    difference_reason character varying(30),
    justification text,
    notes text,
    validated_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT stock_inventories_quantities_check CHECK (((theoretical_quantity >= (0)::numeric) AND (physical_quantity >= (0)::numeric))),
    CONSTRAINT stock_inventories_reason_check CHECK (((difference_reason IS NULL) OR ((difference_reason)::text = ANY ((ARRAY['none'::character varying, 'counting_error'::character varying, 'theft'::character varying, 'damage'::character varying, 'expiry'::character varying, 'unrecorded_use'::character varying, 'unrecorded_entry'::character varying, 'other'::character varying])::text[])))),
    CONSTRAINT stock_inventories_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'pending_validation'::character varying, 'validated'::character varying, 'adjusted'::character varying])::text[])))
);


--
-- Name: stock_inventories_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_inventories_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_inventories_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_inventories_id_seq OWNED BY public.stock_inventories.id;


--
-- Name: stock_lots; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_lots (
    id bigint NOT NULL,
    stock_id bigint NOT NULL,
    supplier_id bigint,
    lot_number character varying(255) NOT NULL,
    internal_lot_number character varying(255),
    manufacturing_date date,
    expiry_date date,
    reception_date date NOT NULL,
    shelf_life_days smallint,
    initial_quantity numeric(12,2) NOT NULL,
    current_quantity numeric(12,2) NOT NULL,
    reserved_quantity numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    unit_price numeric(12,2) NOT NULL,
    total_value numeric(12,2) NOT NULL,
    status character varying(20) DEFAULT 'available'::character varying NOT NULL,
    location character varying(255),
    certificate_number character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT stock_lots_quantities_check CHECK (((initial_quantity >= (0)::numeric) AND (current_quantity >= (0)::numeric) AND (reserved_quantity >= (0)::numeric) AND (unit_price >= (0)::numeric))),
    CONSTRAINT stock_lots_status_check CHECK (((status)::text = ANY ((ARRAY['available'::character varying, 'low'::character varying, 'depleted'::character varying, 'expired'::character varying, 'blocked'::character varying])::text[])))
);


--
-- Name: stock_lots_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_lots_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_lots_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_lots_id_seq OWNED BY public.stock_lots.id;


--
-- Name: stock_movements; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stock_movements (
    id bigint NOT NULL,
    stock_id bigint NOT NULL,
    stock_lot_id bigint,
    user_id bigint,
    flock_id bigint,
    type character varying(20) NOT NULL,
    reason character varying(30) NOT NULL,
    quantity numeric(12,2) NOT NULL,
    unit_price numeric(12,2),
    total_price numeric(12,2),
    movement_date date NOT NULL,
    reference character varying(255),
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT stock_movements_quantity_positive_check CHECK ((quantity > (0)::numeric)),
    CONSTRAINT stock_movements_reason_check CHECK (((reason)::text = ANY ((ARRAY['purchase'::character varying, 'consumption'::character varying, 'sale'::character varying, 'loss'::character varying, 'adjustment'::character varying, 'transfer_in'::character varying, 'transfer_out'::character varying, 'return'::character varying])::text[]))),
    CONSTRAINT stock_movements_type_check CHECK (((type)::text = ANY ((ARRAY['in'::character varying, 'out'::character varying, 'adjustment'::character varying, 'transfer'::character varying])::text[])))
);


--
-- Name: stock_movements_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stock_movements_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stock_movements_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stock_movements_id_seq OWNED BY public.stock_movements.id;


--
-- Name: stocks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.stocks (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    farm_id bigint,
    stock_category_id bigint,
    name character varying(255) NOT NULL,
    code character varying(50),
    description text,
    unit character varying(30) NOT NULL,
    quantity numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    min_quantity numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    unit_price numeric(12,2) DEFAULT '0'::numeric NOT NULL,
    expiry_date date,
    location character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT stocks_quantities_check CHECK (((quantity >= (0)::numeric) AND (min_quantity >= (0)::numeric) AND (unit_price >= (0)::numeric)))
);


--
-- Name: stocks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.stocks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: stocks_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.stocks_id_seq OWNED BY public.stocks.id;


--
-- Name: suppliers; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.suppliers (
    id bigint NOT NULL,
    farm_id bigint,
    name character varying(255) NOT NULL,
    code character varying(50),
    type character varying(50),
    contact_name character varying(255),
    phone character varying(30),
    phone_secondary character varying(30),
    email character varying(255),
    address text,
    tax_id character varying(50),
    bank_name character varying(255),
    bank_account character varying(255),
    payment_terms character varying(50),
    notes text,
    is_active boolean DEFAULT true NOT NULL,
    deleted_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    city_id bigint,
    country_id bigint,
    organisation_id bigint,
    parent_supplier_id bigint,
    CONSTRAINT suppliers_organisation_or_farm_check CHECK (((farm_id IS NOT NULL) OR (organisation_id IS NOT NULL)))
);


--
-- Name: suppliers_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.suppliers_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: suppliers_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.suppliers_id_seq OWNED BY public.suppliers.id;


--
-- Name: transactions; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.transactions (
    id bigint NOT NULL,
    account_id bigint NOT NULL,
    user_id bigint,
    type character varying(10) NOT NULL,
    category character varying(20) NOT NULL,
    amount numeric(15,2) NOT NULL,
    balance_before numeric(15,2) NOT NULL,
    balance_after numeric(15,2) NOT NULL,
    reference_type character varying(255),
    reference_id bigint,
    reference_number character varying(255),
    description character varying(255) NOT NULL,
    notes text,
    transaction_date date NOT NULL,
    is_reconciled boolean DEFAULT false NOT NULL,
    reconciled_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT transactions_category_check CHECK (((category)::text = ANY ((ARRAY['opening'::character varying, 'sale'::character varying, 'payment'::character varying, 'expense'::character varying, 'transfer_in'::character varying, 'transfer_out'::character varying, 'adjustment'::character varying, 'payroll'::character varying, 'refund'::character varying, 'other'::character varying])::text[]))),
    CONSTRAINT transactions_type_check CHECK (((type)::text = ANY ((ARRAY['credit'::character varying, 'debit'::character varying])::text[])))
);


--
-- Name: transactions_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.transactions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: transactions_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.transactions_id_seq OWNED BY public.transactions.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email character varying(255) NOT NULL,
    email_verified_at timestamp(0) without time zone,
    password character varying(255) NOT NULL,
    remember_token character varying(100),
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    phone character varying(20),
    avatar_url character varying(255),
    deleted_at timestamp(0) without time zone,
    type character varying(20) DEFAULT 'farmer'::character varying NOT NULL,
    app_authentication_secret text,
    app_authentication_recovery_codes text,
    CONSTRAINT users_type_check CHECK (((type)::text = ANY ((ARRAY['system'::character varying, 'farmer'::character varying])::text[])))
);


--
-- Name: users_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: users_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;


--
-- Name: vaccinations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vaccinations (
    id bigint NOT NULL,
    flock_id bigint NOT NULL,
    health_program_id bigint,
    administered_by bigint,
    treatment_name character varying(255) NOT NULL,
    treatment_type character varying(255) NOT NULL,
    scheduled_date date NOT NULL,
    administered_date date,
    age_at_treatment integer,
    administration_route character varying(255),
    dosage character varying(255),
    batch_number character varying(255),
    expiry_date date,
    birds_treated integer,
    cost numeric(10,2),
    status character varying(255) DEFAULT 'pending'::character varying NOT NULL,
    observations text,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    withdrawal_days smallint DEFAULT '0'::smallint NOT NULL,
    withdrawal_until date
);


--
-- Name: COLUMN vaccinations.withdrawal_until; Type: COMMENT; Schema: public; Owner: -
--

COMMENT ON COLUMN public.vaccinations.withdrawal_until IS 'Aucune vente ni abattage avant cette date';


--
-- Name: vaccinations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vaccinations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vaccinations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vaccinations_id_seq OWNED BY public.vaccinations.id;


--
-- Name: vet_visits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.vet_visits (
    id bigint NOT NULL,
    farm_id bigint NOT NULL,
    flock_id bigint,
    veterinarian_id bigint,
    recorded_by bigint,
    parent_visit_id bigint,
    visit_date date NOT NULL,
    visit_time time(0) without time zone,
    visit_type character varying(255) NOT NULL,
    reason character varying(255),
    symptoms_observed text,
    diagnosis text,
    birds_examined integer,
    birds_affected integer,
    samples_taken boolean DEFAULT false NOT NULL,
    samples_description text,
    lab_results text,
    prescriptions text,
    recommendations text,
    preventive_measures text,
    consultation_fee numeric(10,2),
    medication_cost numeric(10,2),
    total_cost numeric(10,2),
    is_paid boolean DEFAULT false NOT NULL,
    paid_at date,
    follow_up_required boolean DEFAULT false NOT NULL,
    next_visit_date date,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: vet_visits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.vet_visits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: vet_visits_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.vet_visits_id_seq OWNED BY public.vet_visits.id;


--
-- Name: veterinarians; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.veterinarians (
    id bigint NOT NULL,
    organisation_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    phone character varying(255),
    email character varying(255),
    clinic character varying(255),
    license character varying(255),
    specialty character varying(255),
    is_active boolean DEFAULT true NOT NULL,
    notes text,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);


--
-- Name: veterinarians_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.veterinarians_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: veterinarians_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.veterinarians_id_seq OWNED BY public.veterinarians.id;


--
-- Name: account_transfers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers ALTER COLUMN id SET DEFAULT nextval('public.account_transfers_id_seq'::regclass);


--
-- Name: accounts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounts ALTER COLUMN id SET DEFAULT nextval('public.accounts_id_seq'::regclass);


--
-- Name: activity_log id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log ALTER COLUMN id SET DEFAULT nextval('public.activity_log_id_seq'::regclass);


--
-- Name: alert_dispatches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alert_dispatches ALTER COLUMN id SET DEFAULT nextval('public.alert_dispatches_id_seq'::regclass);


--
-- Name: attendances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendances ALTER COLUMN id SET DEFAULT nextval('public.attendances_id_seq'::regclass);


--
-- Name: buildings id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings ALTER COLUMN id SET DEFAULT nextval('public.buildings_id_seq'::regclass);


--
-- Name: chicken_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chicken_types ALTER COLUMN id SET DEFAULT nextval('public.chicken_types_id_seq'::regclass);


--
-- Name: cities id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities ALTER COLUMN id SET DEFAULT nextval('public.cities_id_seq'::regclass);


--
-- Name: contract_amendments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contract_amendments ALTER COLUMN id SET DEFAULT nextval('public.contract_amendments_id_seq'::regclass);


--
-- Name: contracts id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts ALTER COLUMN id SET DEFAULT nextval('public.contracts_id_seq'::regclass);


--
-- Name: countries id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries ALTER COLUMN id SET DEFAULT nextval('public.countries_id_seq'::regclass);


--
-- Name: credit_notes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes ALTER COLUMN id SET DEFAULT nextval('public.credit_notes_id_seq'::regclass);


--
-- Name: currencies id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies ALTER COLUMN id SET DEFAULT nextval('public.currencies_id_seq'::regclass);


--
-- Name: customers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers ALTER COLUMN id SET DEFAULT nextval('public.customers_id_seq'::regclass);


--
-- Name: daily_records id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_records ALTER COLUMN id SET DEFAULT nextval('public.daily_records_id_seq'::regclass);


--
-- Name: employees id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees ALTER COLUMN id SET DEFAULT nextval('public.employees_id_seq'::regclass);


--
-- Name: equipment_maintenances id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_maintenances ALTER COLUMN id SET DEFAULT nextval('public.equipment_maintenances_id_seq'::regclass);


--
-- Name: equipment_movements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements ALTER COLUMN id SET DEFAULT nextval('public.equipment_movements_id_seq'::regclass);


--
-- Name: equipments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments ALTER COLUMN id SET DEFAULT nextval('public.equipments_id_seq'::regclass);


--
-- Name: expenses id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses ALTER COLUMN id SET DEFAULT nextval('public.expenses_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: farm_logs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs ALTER COLUMN id SET DEFAULT nextval('public.farm_logs_id_seq'::regclass);


--
-- Name: farm_organisation_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_organisation_user ALTER COLUMN id SET DEFAULT nextval('public.farm_organisation_user_id_seq'::regclass);


--
-- Name: farms id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms ALTER COLUMN id SET DEFAULT nextval('public.farms_id_seq'::regclass);


--
-- Name: flocks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flocks ALTER COLUMN id SET DEFAULT nextval('public.flocks_id_seq'::regclass);


--
-- Name: health_incidents id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_incidents ALTER COLUMN id SET DEFAULT nextval('public.health_incidents_id_seq'::regclass);


--
-- Name: health_programs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_programs ALTER COLUMN id SET DEFAULT nextval('public.health_programs_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: leaves id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaves ALTER COLUMN id SET DEFAULT nextval('public.leaves_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: organisation_permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_permissions ALTER COLUMN id SET DEFAULT nextval('public.organisation_permissions_id_seq'::regclass);


--
-- Name: organisation_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_roles ALTER COLUMN id SET DEFAULT nextval('public.organisation_roles_id_seq'::regclass);


--
-- Name: organisation_users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users ALTER COLUMN id SET DEFAULT nextval('public.organisation_users_id_seq'::regclass);


--
-- Name: organisations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations ALTER COLUMN id SET DEFAULT nextval('public.organisations_id_seq'::regclass);


--
-- Name: payments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);


--
-- Name: payroll_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_items ALTER COLUMN id SET DEFAULT nextval('public.payroll_items_id_seq'::regclass);


--
-- Name: payroll_parameters id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_parameters ALTER COLUMN id SET DEFAULT nextval('public.payroll_parameters_id_seq'::regclass);


--
-- Name: payroll_tax_brackets id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_tax_brackets ALTER COLUMN id SET DEFAULT nextval('public.payroll_tax_brackets_id_seq'::regclass);


--
-- Name: payrolls id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls ALTER COLUMN id SET DEFAULT nextval('public.payrolls_id_seq'::regclass);


--
-- Name: plans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans ALTER COLUMN id SET DEFAULT nextval('public.plans_id_seq'::regclass);


--
-- Name: platform_permissions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_permissions ALTER COLUMN id SET DEFAULT nextval('public.platform_permissions_id_seq'::regclass);


--
-- Name: platform_role_permission id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_permission ALTER COLUMN id SET DEFAULT nextval('public.platform_role_permission_id_seq'::regclass);


--
-- Name: platform_role_user id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_user ALTER COLUMN id SET DEFAULT nextval('public.platform_role_user_id_seq'::regclass);


--
-- Name: platform_roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_roles ALTER COLUMN id SET DEFAULT nextval('public.platform_roles_id_seq'::regclass);


--
-- Name: production_phases id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_phases ALTER COLUMN id SET DEFAULT nextval('public.production_phases_id_seq'::regclass);


--
-- Name: purchase_order_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items ALTER COLUMN id SET DEFAULT nextval('public.purchase_order_items_id_seq'::regclass);


--
-- Name: purchase_orders id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders ALTER COLUMN id SET DEFAULT nextval('public.purchase_orders_id_seq'::regclass);


--
-- Name: quote_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quote_items ALTER COLUMN id SET DEFAULT nextval('public.quote_items_id_seq'::regclass);


--
-- Name: quotes id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes ALTER COLUMN id SET DEFAULT nextval('public.quotes_id_seq'::regclass);


--
-- Name: sale_items id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items ALTER COLUMN id SET DEFAULT nextval('public.sale_items_id_seq'::regclass);


--
-- Name: sales id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales ALTER COLUMN id SET DEFAULT nextval('public.sales_id_seq'::regclass);


--
-- Name: sequences id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sequences ALTER COLUMN id SET DEFAULT nextval('public.sequences_id_seq'::regclass);


--
-- Name: stock_categories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_categories ALTER COLUMN id SET DEFAULT nextval('public.stock_categories_id_seq'::regclass);


--
-- Name: stock_inventories id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories ALTER COLUMN id SET DEFAULT nextval('public.stock_inventories_id_seq'::regclass);


--
-- Name: stock_lots id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_lots ALTER COLUMN id SET DEFAULT nextval('public.stock_lots_id_seq'::regclass);


--
-- Name: stock_movements id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements ALTER COLUMN id SET DEFAULT nextval('public.stock_movements_id_seq'::regclass);


--
-- Name: stocks id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks ALTER COLUMN id SET DEFAULT nextval('public.stocks_id_seq'::regclass);


--
-- Name: suppliers id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers ALTER COLUMN id SET DEFAULT nextval('public.suppliers_id_seq'::regclass);


--
-- Name: transactions id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions ALTER COLUMN id SET DEFAULT nextval('public.transactions_id_seq'::regclass);


--
-- Name: users id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);


--
-- Name: vaccinations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vaccinations ALTER COLUMN id SET DEFAULT nextval('public.vaccinations_id_seq'::regclass);


--
-- Name: vet_visits id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits ALTER COLUMN id SET DEFAULT nextval('public.vet_visits_id_seq'::regclass);


--
-- Name: veterinarians id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.veterinarians ALTER COLUMN id SET DEFAULT nextval('public.veterinarians_id_seq'::regclass);


--
-- Name: account_transfers account_transfers_organisation_id_reference_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_organisation_id_reference_unique UNIQUE (organisation_id, reference);


--
-- Name: account_transfers account_transfers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_pkey PRIMARY KEY (id);


--
-- Name: accounts accounts_organisation_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_organisation_id_code_unique UNIQUE (organisation_id, code);


--
-- Name: accounts accounts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (id);


--
-- Name: activity_log activity_log_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.activity_log
    ADD CONSTRAINT activity_log_pkey PRIMARY KEY (id);


--
-- Name: alert_dispatches alert_dispatches_organisation_id_alert_key_dispatched_on_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alert_dispatches
    ADD CONSTRAINT alert_dispatches_organisation_id_alert_key_dispatched_on_unique UNIQUE (organisation_id, alert_key, dispatched_on);


--
-- Name: alert_dispatches alert_dispatches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alert_dispatches
    ADD CONSTRAINT alert_dispatches_pkey PRIMARY KEY (id);


--
-- Name: attendances attendances_employee_id_attendance_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_employee_id_attendance_date_unique UNIQUE (employee_id, attendance_date);


--
-- Name: attendances attendances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_pkey PRIMARY KEY (id);


--
-- Name: buildings buildings_farm_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_farm_code_unique UNIQUE (farm_id, code);


--
-- Name: buildings buildings_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_pkey PRIMARY KEY (id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: chicken_types chicken_types_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chicken_types
    ADD CONSTRAINT chicken_types_name_unique UNIQUE (name);


--
-- Name: chicken_types chicken_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.chicken_types
    ADD CONSTRAINT chicken_types_pkey PRIMARY KEY (id);


--
-- Name: cities cities_name_country_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_name_country_id_unique UNIQUE (name, country_id);


--
-- Name: cities cities_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_pkey PRIMARY KEY (id);


--
-- Name: contract_amendments contract_amendments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contract_amendments
    ADD CONSTRAINT contract_amendments_pkey PRIMARY KEY (id);


--
-- Name: contracts contracts_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_pkey PRIMARY KEY (id);


--
-- Name: countries countries_code_iso3_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_code_iso3_unique UNIQUE (code_iso3);


--
-- Name: countries countries_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_code_unique UNIQUE (code);


--
-- Name: countries countries_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_name_unique UNIQUE (name);


--
-- Name: countries countries_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_pkey PRIMARY KEY (id);


--
-- Name: credit_notes credit_notes_organisation_id_credit_note_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_organisation_id_credit_note_number_unique UNIQUE (organisation_id, credit_note_number);


--
-- Name: credit_notes credit_notes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_pkey PRIMARY KEY (id);


--
-- Name: currencies currencies_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_code_unique UNIQUE (code);


--
-- Name: currencies currencies_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_name_unique UNIQUE (name);


--
-- Name: currencies currencies_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.currencies
    ADD CONSTRAINT currencies_pkey PRIMARY KEY (id);


--
-- Name: customers customers_organisation_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_organisation_id_code_unique UNIQUE (organisation_id, code);


--
-- Name: customers customers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_pkey PRIMARY KEY (id);


--
-- Name: daily_records daily_records_flock_id_record_date_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_records
    ADD CONSTRAINT daily_records_flock_id_record_date_unique UNIQUE (flock_id, record_date);


--
-- Name: daily_records daily_records_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_records
    ADD CONSTRAINT daily_records_pkey PRIMARY KEY (id);


--
-- Name: employees employees_organisation_id_employee_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_organisation_id_employee_number_unique UNIQUE (organisation_id, employee_number);


--
-- Name: employees employees_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_pkey PRIMARY KEY (id);


--
-- Name: equipment_maintenances equipment_maintenances_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_maintenances
    ADD CONSTRAINT equipment_maintenances_pkey PRIMARY KEY (id);


--
-- Name: equipment_movements equipment_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements
    ADD CONSTRAINT equipment_movements_pkey PRIMARY KEY (id);


--
-- Name: equipments equipments_farm_inventory_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments
    ADD CONSTRAINT equipments_farm_inventory_unique UNIQUE (farm_id, inventory_number);


--
-- Name: equipments equipments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments
    ADD CONSTRAINT equipments_pkey PRIMARY KEY (id);


--
-- Name: expenses expenses_organisation_id_expense_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_organisation_id_expense_number_unique UNIQUE (organisation_id, expense_number);


--
-- Name: expenses expenses_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: farm_logs farm_logs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_pkey PRIMARY KEY (id);


--
-- Name: farm_organisation_user farm_organisation_user_farm_id_organisation_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_organisation_user
    ADD CONSTRAINT farm_organisation_user_farm_id_organisation_user_id_unique UNIQUE (farm_id, organisation_user_id);


--
-- Name: farm_organisation_user farm_organisation_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_organisation_user
    ADD CONSTRAINT farm_organisation_user_pkey PRIMARY KEY (id);


--
-- Name: farms farms_organisation_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_organisation_code_unique UNIQUE (organisation_id, code);


--
-- Name: farms farms_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_pkey PRIMARY KEY (id);


--
-- Name: flocks flocks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flocks
    ADD CONSTRAINT flocks_pkey PRIMARY KEY (id);


--
-- Name: health_incidents health_incidents_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_incidents
    ADD CONSTRAINT health_incidents_pkey PRIMARY KEY (id);


--
-- Name: health_programs health_programs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_programs
    ADD CONSTRAINT health_programs_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: leaves leaves_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaves
    ADD CONSTRAINT leaves_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: notifications notifications_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.notifications
    ADD CONSTRAINT notifications_pkey PRIMARY KEY (id);


--
-- Name: organisation_permissions organisation_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_permissions
    ADD CONSTRAINT organisation_permissions_pkey PRIMARY KEY (id);


--
-- Name: organisation_permissions organisation_permissions_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_permissions
    ADD CONSTRAINT organisation_permissions_slug_unique UNIQUE (slug);


--
-- Name: organisation_role_permission organisation_role_permission_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_role_permission
    ADD CONSTRAINT organisation_role_permission_pkey PRIMARY KEY (organisation_role_id, organisation_permission_id);


--
-- Name: organisation_roles organisation_roles_organisation_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_roles
    ADD CONSTRAINT organisation_roles_organisation_id_slug_unique UNIQUE (organisation_id, slug);


--
-- Name: organisation_roles organisation_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_roles
    ADD CONSTRAINT organisation_roles_pkey PRIMARY KEY (id);


--
-- Name: organisation_users organisation_users_organisation_id_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users
    ADD CONSTRAINT organisation_users_organisation_id_user_id_unique UNIQUE (organisation_id, user_id);


--
-- Name: organisation_users organisation_users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users
    ADD CONSTRAINT organisation_users_pkey PRIMARY KEY (id);


--
-- Name: organisations organisations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations
    ADD CONSTRAINT organisations_pkey PRIMARY KEY (id);


--
-- Name: organisations organisations_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations
    ADD CONSTRAINT organisations_slug_unique UNIQUE (slug);


--
-- Name: password_reset_tokens password_reset_tokens_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.password_reset_tokens
    ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email);


--
-- Name: payments payments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);


--
-- Name: payroll_items payroll_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_items
    ADD CONSTRAINT payroll_items_pkey PRIMARY KEY (id);


--
-- Name: payroll_parameters payroll_parameters_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_parameters
    ADD CONSTRAINT payroll_parameters_pkey PRIMARY KEY (id);


--
-- Name: payroll_tax_brackets payroll_tax_brackets_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_tax_brackets
    ADD CONSTRAINT payroll_tax_brackets_pkey PRIMARY KEY (id);


--
-- Name: payrolls payrolls_employee_id_period_year_period_month_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_employee_id_period_year_period_month_unique UNIQUE (employee_id, period_year, period_month);


--
-- Name: payrolls payrolls_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_pkey PRIMARY KEY (id);


--
-- Name: plans plans_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_name_unique UNIQUE (name);


--
-- Name: plans plans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);


--
-- Name: plans plans_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_slug_unique UNIQUE (slug);


--
-- Name: platform_permissions platform_permissions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_permissions
    ADD CONSTRAINT platform_permissions_pkey PRIMARY KEY (id);


--
-- Name: platform_permissions platform_permissions_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_permissions
    ADD CONSTRAINT platform_permissions_slug_unique UNIQUE (slug);


--
-- Name: platform_role_permission platform_role_permission_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_permission
    ADD CONSTRAINT platform_role_permission_pkey PRIMARY KEY (id);


--
-- Name: platform_role_user platform_role_user_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_user
    ADD CONSTRAINT platform_role_user_pkey PRIMARY KEY (id);


--
-- Name: platform_role_user platform_role_user_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_user
    ADD CONSTRAINT platform_role_user_user_id_unique UNIQUE (user_id);


--
-- Name: platform_roles platform_roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_roles
    ADD CONSTRAINT platform_roles_pkey PRIMARY KEY (id);


--
-- Name: platform_roles platform_roles_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_roles
    ADD CONSTRAINT platform_roles_slug_unique UNIQUE (slug);


--
-- Name: production_phases production_phases_organisation_id_chicken_type_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_phases
    ADD CONSTRAINT production_phases_organisation_id_chicken_type_id_code_unique UNIQUE (organisation_id, chicken_type_id, code);


--
-- Name: production_phases production_phases_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_phases
    ADD CONSTRAINT production_phases_pkey PRIMARY KEY (id);


--
-- Name: purchase_order_items purchase_order_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_pkey PRIMARY KEY (id);


--
-- Name: purchase_orders purchase_orders_organisation_id_order_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_organisation_id_order_number_unique UNIQUE (organisation_id, order_number);


--
-- Name: purchase_orders purchase_orders_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_pkey PRIMARY KEY (id);


--
-- Name: quote_items quote_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_pkey PRIMARY KEY (id);


--
-- Name: quotes quotes_organisation_id_quote_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_organisation_id_quote_number_unique UNIQUE (organisation_id, quote_number);


--
-- Name: quotes quotes_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_pkey PRIMARY KEY (id);


--
-- Name: platform_role_permission role_permission_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_permission
    ADD CONSTRAINT role_permission_unique UNIQUE (platform_role_id, platform_permission_id);


--
-- Name: sale_items sale_items_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_pkey PRIMARY KEY (id);


--
-- Name: sales sales_organisation_id_invoice_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_organisation_id_invoice_number_unique UNIQUE (organisation_id, invoice_number);


--
-- Name: sales sales_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_pkey PRIMARY KEY (id);


--
-- Name: sequences sequences_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sequences
    ADD CONSTRAINT sequences_pkey PRIMARY KEY (id);


--
-- Name: sequences sequences_scope_scope_id_key_year_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sequences
    ADD CONSTRAINT sequences_scope_scope_id_key_year_unique UNIQUE (scope, scope_id, key, year);


--
-- Name: sessions sessions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);


--
-- Name: stock_categories stock_categories_organisation_id_slug_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_categories
    ADD CONSTRAINT stock_categories_organisation_id_slug_unique UNIQUE (organisation_id, slug);


--
-- Name: stock_categories stock_categories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_categories
    ADD CONSTRAINT stock_categories_pkey PRIMARY KEY (id);


--
-- Name: stock_inventories stock_inventories_organisation_id_inventory_ref_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_organisation_id_inventory_ref_unique UNIQUE (organisation_id, inventory_ref);


--
-- Name: stock_inventories stock_inventories_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_pkey PRIMARY KEY (id);


--
-- Name: stock_lots stock_lots_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_lots
    ADD CONSTRAINT stock_lots_pkey PRIMARY KEY (id);


--
-- Name: stock_lots stock_lots_stock_id_lot_number_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_lots
    ADD CONSTRAINT stock_lots_stock_id_lot_number_unique UNIQUE (stock_id, lot_number);


--
-- Name: stock_movements stock_movements_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_pkey PRIMARY KEY (id);


--
-- Name: stocks stocks_organisation_id_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_organisation_id_code_unique UNIQUE (organisation_id, code);


--
-- Name: stocks stocks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_pkey PRIMARY KEY (id);


--
-- Name: suppliers suppliers_organisation_code_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_organisation_code_unique UNIQUE (organisation_id, code);


--
-- Name: suppliers suppliers_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_pkey PRIMARY KEY (id);


--
-- Name: transactions transactions_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_pkey PRIMARY KEY (id);


--
-- Name: users users_email_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: vaccinations vaccinations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vaccinations
    ADD CONSTRAINT vaccinations_pkey PRIMARY KEY (id);


--
-- Name: vet_visits vet_visits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_pkey PRIMARY KEY (id);


--
-- Name: veterinarians veterinarians_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.veterinarians
    ADD CONSTRAINT veterinarians_pkey PRIMARY KEY (id);


--
-- Name: account_transfers_organisation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_transfers_organisation_id_status_index ON public.account_transfers USING btree (organisation_id, status);


--
-- Name: account_transfers_organisation_id_transfer_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX account_transfers_organisation_id_transfer_date_index ON public.account_transfers USING btree (organisation_id, transfer_date);


--
-- Name: accounts_farm_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accounts_farm_id_is_active_index ON public.accounts USING btree (farm_id, is_active);


--
-- Name: accounts_organisation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX accounts_organisation_id_is_active_index ON public.accounts USING btree (organisation_id, is_active);


--
-- Name: activity_log_log_name_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX activity_log_log_name_index ON public.activity_log USING btree (log_name);


--
-- Name: attendances_employee_id_attendance_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX attendances_employee_id_attendance_date_index ON public.attendances USING btree (employee_id, attendance_date);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: causer; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX causer ON public.activity_log USING btree (causer_type, causer_id);


--
-- Name: contracts_employee_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contracts_employee_id_status_index ON public.contracts USING btree (employee_id, status);


--
-- Name: contracts_status_end_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX contracts_status_end_date_index ON public.contracts USING btree (status, end_date);


--
-- Name: credit_notes_organisation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_notes_organisation_id_status_index ON public.credit_notes USING btree (organisation_id, status);


--
-- Name: credit_notes_sale_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX credit_notes_sale_id_status_index ON public.credit_notes USING btree (sale_id, status);


--
-- Name: customers_farm_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customers_farm_id_is_active_index ON public.customers USING btree (farm_id, is_active);


--
-- Name: customers_organisation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX customers_organisation_id_is_active_index ON public.customers USING btree (organisation_id, is_active);


--
-- Name: daily_records_flock_id_record_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX daily_records_flock_id_record_date_index ON public.daily_records USING btree (flock_id, record_date);


--
-- Name: employees_farm_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_farm_id_status_index ON public.employees USING btree (farm_id, status);


--
-- Name: employees_organisation_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX employees_organisation_id_status_index ON public.employees USING btree (organisation_id, status);


--
-- Name: equipment_maintenances_next_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_maintenances_next_date_index ON public.equipment_maintenances USING btree (next_maintenance_date);


--
-- Name: equipment_maintenances_scheduled_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_maintenances_scheduled_date_index ON public.equipment_maintenances USING btree (scheduled_date);


--
-- Name: equipment_maintenances_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_maintenances_status_index ON public.equipment_maintenances USING btree (status);


--
-- Name: equipment_movements_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX equipment_movements_date_index ON public.equipment_movements USING btree (movement_date);


--
-- Name: expenses_farm_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_farm_id_status_index ON public.expenses USING btree (farm_id, status);


--
-- Name: expenses_flock_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_flock_id_status_index ON public.expenses USING btree (flock_id, status);


--
-- Name: expenses_organisation_id_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_organisation_id_category_index ON public.expenses USING btree (organisation_id, category);


--
-- Name: expenses_organisation_id_expense_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_organisation_id_expense_date_index ON public.expenses USING btree (organisation_id, expense_date);


--
-- Name: expenses_status_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX expenses_status_due_date_index ON public.expenses USING btree (status, due_date);


--
-- Name: farm_logs_farm_id_category_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX farm_logs_farm_id_category_index ON public.farm_logs USING btree (farm_id, category);


--
-- Name: farm_logs_farm_id_log_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX farm_logs_farm_id_log_date_index ON public.farm_logs USING btree (farm_id, log_date);


--
-- Name: farm_logs_flock_id_log_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX farm_logs_flock_id_log_date_index ON public.farm_logs USING btree (flock_id, log_date);


--
-- Name: flocks_building_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX flocks_building_status_index ON public.flocks USING btree (building_id, status);


--
-- Name: flocks_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX flocks_status_index ON public.flocks USING btree (status);


--
-- Name: health_incidents_category_severity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX health_incidents_category_severity_index ON public.health_incidents USING btree (category, severity);


--
-- Name: health_incidents_flock_id_incident_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX health_incidents_flock_id_incident_date_index ON public.health_incidents USING btree (flock_id, incident_date);


--
-- Name: health_incidents_flock_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX health_incidents_flock_id_status_index ON public.health_incidents USING btree (flock_id, status);


--
-- Name: hp_farm_day_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hp_farm_day_index ON public.health_programs USING btree (farm_id, scheduled_day);


--
-- Name: hp_org_bird_day_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX hp_org_bird_day_index ON public.health_programs USING btree (organisation_id, chicken_type_id, scheduled_day);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: leaves_employee_id_start_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX leaves_employee_id_start_date_index ON public.leaves USING btree (employee_id, start_date);


--
-- Name: leaves_employee_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX leaves_employee_id_status_index ON public.leaves USING btree (employee_id, status);


--
-- Name: notifications_notifiable_type_notifiable_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX notifications_notifiable_type_notifiable_id_index ON public.notifications USING btree (notifiable_type, notifiable_id);


--
-- Name: organisation_users_role_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX organisation_users_role_index ON public.organisation_users USING btree (organisation_role_id);


--
-- Name: payments_sale_id_payment_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payments_sale_id_payment_date_index ON public.payments USING btree (sale_id, payment_date);


--
-- Name: payroll_items_payroll_id_is_deduction_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payroll_items_payroll_id_is_deduction_index ON public.payroll_items USING btree (payroll_id, is_deduction);


--
-- Name: payroll_parameters_organisation_id_effective_from_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payroll_parameters_organisation_id_effective_from_index ON public.payroll_parameters USING btree (organisation_id, effective_from);


--
-- Name: payroll_tax_brackets_payroll_parameter_id_order_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payroll_tax_brackets_payroll_parameter_id_order_index ON public.payroll_tax_brackets USING btree (payroll_parameter_id, "order");


--
-- Name: payrolls_status_period_year_period_month_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX payrolls_status_period_year_period_month_index ON public.payrolls USING btree (status, period_year, period_month);


--
-- Name: production_phases_organisation_id_chicken_type_id_start_day_ind; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX production_phases_organisation_id_chicken_type_id_start_day_ind ON public.production_phases USING btree (organisation_id, chicken_type_id, start_day);


--
-- Name: purchase_order_items_purchase_order_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_order_items_purchase_order_id_status_index ON public.purchase_order_items USING btree (purchase_order_id, status);


--
-- Name: purchase_orders_organisation_id_order_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_organisation_id_order_date_index ON public.purchase_orders USING btree (organisation_id, order_date);


--
-- Name: purchase_orders_supplier_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX purchase_orders_supplier_id_status_index ON public.purchase_orders USING btree (supplier_id, status);


--
-- Name: quote_items_quote_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quote_items_quote_id_index ON public.quote_items USING btree (quote_id);


--
-- Name: quotes_converted_sale_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quotes_converted_sale_id_index ON public.quotes USING btree (converted_sale_id);


--
-- Name: quotes_customer_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quotes_customer_id_status_index ON public.quotes USING btree (customer_id, status);


--
-- Name: quotes_organisation_id_quote_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX quotes_organisation_id_quote_date_index ON public.quotes USING btree (organisation_id, quote_date);


--
-- Name: sale_items_sale_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sale_items_sale_id_index ON public.sale_items USING btree (sale_id);


--
-- Name: sales_customer_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_customer_id_status_index ON public.sales USING btree (customer_id, status);


--
-- Name: sales_organisation_id_sale_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_organisation_id_sale_date_index ON public.sales USING btree (organisation_id, sale_date);


--
-- Name: sales_status_due_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sales_status_due_date_index ON public.sales USING btree (status, due_date);


--
-- Name: sessions_last_activity_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);


--
-- Name: sessions_user_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);


--
-- Name: stock_categories_type_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_categories_type_index ON public.stock_categories USING btree (type);


--
-- Name: stock_inventories_organisation_id_inventory_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_inventories_organisation_id_inventory_date_index ON public.stock_inventories USING btree (organisation_id, inventory_date);


--
-- Name: stock_inventories_stock_id_inventory_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_inventories_stock_id_inventory_date_index ON public.stock_inventories USING btree (stock_id, inventory_date);


--
-- Name: stock_lots_status_expiry_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_lots_status_expiry_date_index ON public.stock_lots USING btree (status, expiry_date);


--
-- Name: stock_lots_stock_id_expiry_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_lots_stock_id_expiry_date_index ON public.stock_lots USING btree (stock_id, expiry_date);


--
-- Name: stock_movements_flock_id_movement_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_flock_id_movement_date_index ON public.stock_movements USING btree (flock_id, movement_date);


--
-- Name: stock_movements_stock_id_movement_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_stock_id_movement_date_index ON public.stock_movements USING btree (stock_id, movement_date);


--
-- Name: stock_movements_type_reason_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stock_movements_type_reason_index ON public.stock_movements USING btree (type, reason);


--
-- Name: stocks_farm_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stocks_farm_id_is_active_index ON public.stocks USING btree (farm_id, is_active);


--
-- Name: stocks_organisation_id_is_active_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX stocks_organisation_id_is_active_index ON public.stocks USING btree (organisation_id, is_active);


--
-- Name: subject; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX subject ON public.activity_log USING btree (subject_type, subject_id);


--
-- Name: transactions_account_id_is_reconciled_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX transactions_account_id_is_reconciled_index ON public.transactions USING btree (account_id, is_reconciled);


--
-- Name: transactions_account_id_transaction_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX transactions_account_id_transaction_date_index ON public.transactions USING btree (account_id, transaction_date);


--
-- Name: transactions_reference_type_reference_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX transactions_reference_type_reference_id_index ON public.transactions USING btree (reference_type, reference_id);


--
-- Name: vaccinations_flock_id_scheduled_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vaccinations_flock_id_scheduled_date_index ON public.vaccinations USING btree (flock_id, scheduled_date);


--
-- Name: vaccinations_flock_id_status_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vaccinations_flock_id_status_index ON public.vaccinations USING btree (flock_id, status);


--
-- Name: vaccinations_withdrawal_until_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vaccinations_withdrawal_until_index ON public.vaccinations USING btree (withdrawal_until);


--
-- Name: vet_visits_farm_id_visit_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vet_visits_farm_id_visit_date_index ON public.vet_visits USING btree (farm_id, visit_date);


--
-- Name: vet_visits_flock_id_visit_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vet_visits_flock_id_visit_date_index ON public.vet_visits USING btree (flock_id, visit_date);


--
-- Name: vet_visits_veterinarian_id_visit_date_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX vet_visits_veterinarian_id_visit_date_index ON public.vet_visits USING btree (veterinarian_id, visit_date);


--
-- Name: veterinarians_organisation_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX veterinarians_organisation_id_index ON public.veterinarians USING btree (organisation_id);


--
-- Name: account_transfers account_transfers_from_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_from_account_id_foreign FOREIGN KEY (from_account_id) REFERENCES public.accounts(id) ON DELETE RESTRICT;


--
-- Name: account_transfers account_transfers_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: account_transfers account_transfers_to_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_to_account_id_foreign FOREIGN KEY (to_account_id) REFERENCES public.accounts(id) ON DELETE RESTRICT;


--
-- Name: account_transfers account_transfers_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.account_transfers
    ADD CONSTRAINT account_transfers_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: accounts accounts_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: accounts accounts_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.accounts
    ADD CONSTRAINT accounts_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: alert_dispatches alert_dispatches_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.alert_dispatches
    ADD CONSTRAINT alert_dispatches_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: attendances attendances_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE RESTRICT;


--
-- Name: attendances attendances_recorded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.attendances
    ADD CONSTRAINT attendances_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: buildings buildings_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.buildings
    ADD CONSTRAINT buildings_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: cities cities_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cities
    ADD CONSTRAINT cities_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id) ON DELETE CASCADE;


--
-- Name: contract_amendments contract_amendments_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contract_amendments
    ADD CONSTRAINT contract_amendments_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES public.contracts(id) ON DELETE CASCADE;


--
-- Name: contracts contracts_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE RESTRICT;


--
-- Name: contracts contracts_renewed_by_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.contracts
    ADD CONSTRAINT contracts_renewed_by_contract_id_foreign FOREIGN KEY (renewed_by_contract_id) REFERENCES public.contracts(id) ON DELETE SET NULL;


--
-- Name: countries countries_currency_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.countries
    ADD CONSTRAINT countries_currency_id_foreign FOREIGN KEY (currency_id) REFERENCES public.currencies(id) ON DELETE SET NULL;


--
-- Name: credit_notes credit_notes_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: credit_notes credit_notes_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: credit_notes credit_notes_refund_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_refund_account_id_foreign FOREIGN KEY (refund_account_id) REFERENCES public.accounts(id) ON DELETE SET NULL;


--
-- Name: credit_notes credit_notes_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: credit_notes credit_notes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.credit_notes
    ADD CONSTRAINT credit_notes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: customers customers_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL;


--
-- Name: customers customers_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: customers customers_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.customers
    ADD CONSTRAINT customers_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: daily_records daily_records_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_records
    ADD CONSTRAINT daily_records_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE RESTRICT;


--
-- Name: daily_records daily_records_recorded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.daily_records
    ADD CONSTRAINT daily_records_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: employees employees_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: employees employees_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: employees employees_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.employees
    ADD CONSTRAINT employees_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_maintenances equipment_maintenances_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_maintenances
    ADD CONSTRAINT equipment_maintenances_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipments(id) ON DELETE CASCADE;


--
-- Name: equipment_maintenances equipment_maintenances_performed_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_maintenances
    ADD CONSTRAINT equipment_maintenances_performed_by_foreign FOREIGN KEY (performed_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_movements equipment_movements_equipment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements
    ADD CONSTRAINT equipment_movements_equipment_id_foreign FOREIGN KEY (equipment_id) REFERENCES public.equipments(id) ON DELETE CASCADE;


--
-- Name: equipment_movements equipment_movements_from_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements
    ADD CONSTRAINT equipment_movements_from_building_id_foreign FOREIGN KEY (from_building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: equipment_movements equipment_movements_moved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements
    ADD CONSTRAINT equipment_movements_moved_by_foreign FOREIGN KEY (moved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: equipment_movements equipment_movements_to_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipment_movements
    ADD CONSTRAINT equipment_movements_to_building_id_foreign FOREIGN KEY (to_building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: equipments equipments_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments
    ADD CONSTRAINT equipments_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE CASCADE;


--
-- Name: equipments equipments_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments
    ADD CONSTRAINT equipments_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: equipments equipments_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.equipments
    ADD CONSTRAINT equipments_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.accounts(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: expenses expenses_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: expenses expenses_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.expenses
    ADD CONSTRAINT expenses_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: farm_logs farm_logs_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE SET NULL;


--
-- Name: farm_logs farm_logs_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: farm_logs farm_logs_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: farm_logs farm_logs_recorded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES public.users(id);


--
-- Name: farm_logs farm_logs_resolved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_logs
    ADD CONSTRAINT farm_logs_resolved_by_foreign FOREIGN KEY (resolved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: farm_organisation_user farm_organisation_user_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_organisation_user
    ADD CONSTRAINT farm_organisation_user_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: farm_organisation_user farm_organisation_user_organisation_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farm_organisation_user
    ADD CONSTRAINT farm_organisation_user_organisation_user_id_foreign FOREIGN KEY (organisation_user_id) REFERENCES public.organisation_users(id) ON DELETE CASCADE;


--
-- Name: farms farms_chicken_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_chicken_type_id_foreign FOREIGN KEY (chicken_type_id) REFERENCES public.chicken_types(id) ON DELETE SET NULL;


--
-- Name: farms farms_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL;


--
-- Name: farms farms_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.farms
    ADD CONSTRAINT farms_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: flocks flocks_building_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flocks
    ADD CONSTRAINT flocks_building_id_foreign FOREIGN KEY (building_id) REFERENCES public.buildings(id) ON DELETE CASCADE;


--
-- Name: flocks flocks_chicken_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flocks
    ADD CONSTRAINT flocks_chicken_type_id_foreign FOREIGN KEY (chicken_type_id) REFERENCES public.chicken_types(id) ON DELETE SET NULL;


--
-- Name: flocks flocks_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.flocks
    ADD CONSTRAINT flocks_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: health_incidents health_incidents_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_incidents
    ADD CONSTRAINT health_incidents_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE RESTRICT;


--
-- Name: health_incidents health_incidents_reported_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_incidents
    ADD CONSTRAINT health_incidents_reported_by_foreign FOREIGN KEY (reported_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: health_programs health_programs_chicken_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_programs
    ADD CONSTRAINT health_programs_chicken_type_id_foreign FOREIGN KEY (chicken_type_id) REFERENCES public.chicken_types(id) ON DELETE SET NULL;


--
-- Name: health_programs health_programs_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_programs
    ADD CONSTRAINT health_programs_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: health_programs health_programs_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.health_programs
    ADD CONSTRAINT health_programs_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE SET NULL;


--
-- Name: leaves leaves_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaves
    ADD CONSTRAINT leaves_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: leaves leaves_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.leaves
    ADD CONSTRAINT leaves_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE RESTRICT;


--
-- Name: organisation_role_permission organisation_role_permission_organisation_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_role_permission
    ADD CONSTRAINT organisation_role_permission_organisation_permission_id_foreign FOREIGN KEY (organisation_permission_id) REFERENCES public.organisation_permissions(id) ON DELETE CASCADE;


--
-- Name: organisation_role_permission organisation_role_permission_organisation_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_role_permission
    ADD CONSTRAINT organisation_role_permission_organisation_role_id_foreign FOREIGN KEY (organisation_role_id) REFERENCES public.organisation_roles(id) ON DELETE CASCADE;


--
-- Name: organisation_roles organisation_roles_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_roles
    ADD CONSTRAINT organisation_roles_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: organisation_users organisation_users_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users
    ADD CONSTRAINT organisation_users_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: organisation_users organisation_users_organisation_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users
    ADD CONSTRAINT organisation_users_organisation_role_id_foreign FOREIGN KEY (organisation_role_id) REFERENCES public.organisation_roles(id) ON DELETE SET NULL;


--
-- Name: organisation_users organisation_users_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisation_users
    ADD CONSTRAINT organisation_users_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: organisations organisations_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations
    ADD CONSTRAINT organisations_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL;


--
-- Name: organisations organisations_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations
    ADD CONSTRAINT organisations_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id) ON DELETE SET NULL;


--
-- Name: organisations organisations_plan_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.organisations
    ADD CONSTRAINT organisations_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.plans(id) ON DELETE SET NULL;


--
-- Name: payments payments_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.accounts(id) ON DELETE SET NULL;


--
-- Name: payments payments_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE RESTRICT;


--
-- Name: payments payments_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: payroll_items payroll_items_payroll_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_items
    ADD CONSTRAINT payroll_items_payroll_id_foreign FOREIGN KEY (payroll_id) REFERENCES public.payrolls(id) ON DELETE CASCADE;


--
-- Name: payroll_parameters payroll_parameters_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_parameters
    ADD CONSTRAINT payroll_parameters_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: payroll_tax_brackets payroll_tax_brackets_payroll_parameter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payroll_tax_brackets
    ADD CONSTRAINT payroll_tax_brackets_payroll_parameter_id_foreign FOREIGN KEY (payroll_parameter_id) REFERENCES public.payroll_parameters(id) ON DELETE CASCADE;


--
-- Name: payrolls payrolls_contract_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_contract_id_foreign FOREIGN KEY (contract_id) REFERENCES public.contracts(id) ON DELETE SET NULL;


--
-- Name: payrolls payrolls_employee_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_employee_id_foreign FOREIGN KEY (employee_id) REFERENCES public.employees(id) ON DELETE RESTRICT;


--
-- Name: payrolls payrolls_expense_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_expense_id_foreign FOREIGN KEY (expense_id) REFERENCES public.expenses(id) ON DELETE SET NULL;


--
-- Name: payrolls payrolls_paid_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_paid_by_foreign FOREIGN KEY (paid_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: payrolls payrolls_payroll_parameter_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_payroll_parameter_id_foreign FOREIGN KEY (payroll_parameter_id) REFERENCES public.payroll_parameters(id) ON DELETE SET NULL;


--
-- Name: payrolls payrolls_validated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.payrolls
    ADD CONSTRAINT payrolls_validated_by_foreign FOREIGN KEY (validated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: platform_role_permission platform_role_permission_platform_permission_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_permission
    ADD CONSTRAINT platform_role_permission_platform_permission_id_foreign FOREIGN KEY (platform_permission_id) REFERENCES public.platform_permissions(id) ON DELETE CASCADE;


--
-- Name: platform_role_permission platform_role_permission_platform_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_permission
    ADD CONSTRAINT platform_role_permission_platform_role_id_foreign FOREIGN KEY (platform_role_id) REFERENCES public.platform_roles(id) ON DELETE CASCADE;


--
-- Name: platform_role_user platform_role_user_platform_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_user
    ADD CONSTRAINT platform_role_user_platform_role_id_foreign FOREIGN KEY (platform_role_id) REFERENCES public.platform_roles(id) ON DELETE CASCADE;


--
-- Name: platform_role_user platform_role_user_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.platform_role_user
    ADD CONSTRAINT platform_role_user_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: production_phases production_phases_chicken_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_phases
    ADD CONSTRAINT production_phases_chicken_type_id_foreign FOREIGN KEY (chicken_type_id) REFERENCES public.chicken_types(id) ON DELETE CASCADE;


--
-- Name: production_phases production_phases_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.production_phases
    ADD CONSTRAINT production_phases_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE SET NULL;


--
-- Name: purchase_order_items purchase_order_items_purchase_order_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_purchase_order_id_foreign FOREIGN KEY (purchase_order_id) REFERENCES public.purchase_orders(id) ON DELETE CASCADE;


--
-- Name: purchase_order_items purchase_order_items_stock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_stock_id_foreign FOREIGN KEY (stock_id) REFERENCES public.stocks(id) ON DELETE SET NULL;


--
-- Name: purchase_order_items purchase_order_items_stock_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_order_items
    ADD CONSTRAINT purchase_order_items_stock_lot_id_foreign FOREIGN KEY (stock_lot_id) REFERENCES public.stock_lots(id) ON DELETE SET NULL;


--
-- Name: purchase_orders purchase_orders_approved_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_approved_by_foreign FOREIGN KEY (approved_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_orders purchase_orders_created_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_created_by_foreign FOREIGN KEY (created_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: purchase_orders purchase_orders_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: purchase_orders purchase_orders_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: purchase_orders purchase_orders_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.purchase_orders
    ADD CONSTRAINT purchase_orders_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE RESTRICT;


--
-- Name: quote_items quote_items_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: quote_items quote_items_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quote_items
    ADD CONSTRAINT quote_items_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES public.quotes(id) ON DELETE CASCADE;


--
-- Name: quotes quotes_converted_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_converted_sale_id_foreign FOREIGN KEY (converted_sale_id) REFERENCES public.sales(id) ON DELETE SET NULL;


--
-- Name: quotes quotes_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: quotes quotes_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: quotes quotes_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: quotes quotes_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.quotes
    ADD CONSTRAINT quotes_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: sale_items sale_items_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: sale_items sale_items_sale_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sale_items
    ADD CONSTRAINT sale_items_sale_id_foreign FOREIGN KEY (sale_id) REFERENCES public.sales(id) ON DELETE CASCADE;


--
-- Name: sales sales_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.accounts(id) ON DELETE SET NULL;


--
-- Name: sales sales_customer_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_customer_id_foreign FOREIGN KEY (customer_id) REFERENCES public.customers(id) ON DELETE SET NULL;


--
-- Name: sales sales_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: sales sales_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: sales sales_quote_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_quote_id_foreign FOREIGN KEY (quote_id) REFERENCES public.quotes(id) ON DELETE SET NULL;


--
-- Name: sales sales_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.sales
    ADD CONSTRAINT sales_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: stock_categories stock_categories_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_categories
    ADD CONSTRAINT stock_categories_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: stock_inventories stock_inventories_adjustment_movement_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_adjustment_movement_id_foreign FOREIGN KEY (adjustment_movement_id) REFERENCES public.stock_movements(id) ON DELETE SET NULL;


--
-- Name: stock_inventories stock_inventories_conducted_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_conducted_by_foreign FOREIGN KEY (conducted_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: stock_inventories stock_inventories_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: stock_inventories stock_inventories_stock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_stock_id_foreign FOREIGN KEY (stock_id) REFERENCES public.stocks(id) ON DELETE CASCADE;


--
-- Name: stock_inventories stock_inventories_stock_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_stock_lot_id_foreign FOREIGN KEY (stock_lot_id) REFERENCES public.stock_lots(id) ON DELETE SET NULL;


--
-- Name: stock_inventories stock_inventories_validated_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_inventories
    ADD CONSTRAINT stock_inventories_validated_by_foreign FOREIGN KEY (validated_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: stock_lots stock_lots_stock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_lots
    ADD CONSTRAINT stock_lots_stock_id_foreign FOREIGN KEY (stock_id) REFERENCES public.stocks(id) ON DELETE RESTRICT;


--
-- Name: stock_lots stock_lots_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_lots
    ADD CONSTRAINT stock_lots_supplier_id_foreign FOREIGN KEY (supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: stock_movements stock_movements_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: stock_movements stock_movements_stock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_stock_id_foreign FOREIGN KEY (stock_id) REFERENCES public.stocks(id) ON DELETE RESTRICT;


--
-- Name: stock_movements stock_movements_stock_lot_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_stock_lot_id_foreign FOREIGN KEY (stock_lot_id) REFERENCES public.stock_lots(id) ON DELETE SET NULL;


--
-- Name: stock_movements stock_movements_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stock_movements
    ADD CONSTRAINT stock_movements_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: stocks stocks_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE SET NULL;


--
-- Name: stocks stocks_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- Name: stocks stocks_stock_category_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.stocks
    ADD CONSTRAINT stocks_stock_category_id_foreign FOREIGN KEY (stock_category_id) REFERENCES public.stock_categories(id) ON DELETE SET NULL;


--
-- Name: suppliers suppliers_city_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_city_id_foreign FOREIGN KEY (city_id) REFERENCES public.cities(id) ON DELETE SET NULL;


--
-- Name: suppliers suppliers_country_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_country_id_foreign FOREIGN KEY (country_id) REFERENCES public.countries(id) ON DELETE SET NULL;


--
-- Name: suppliers suppliers_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: suppliers suppliers_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE SET NULL;


--
-- Name: suppliers suppliers_parent_supplier_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.suppliers
    ADD CONSTRAINT suppliers_parent_supplier_id_foreign FOREIGN KEY (parent_supplier_id) REFERENCES public.suppliers(id) ON DELETE SET NULL;


--
-- Name: transactions transactions_account_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_account_id_foreign FOREIGN KEY (account_id) REFERENCES public.accounts(id) ON DELETE RESTRICT;


--
-- Name: transactions transactions_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.transactions
    ADD CONSTRAINT transactions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: vaccinations vaccinations_administered_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vaccinations
    ADD CONSTRAINT vaccinations_administered_by_foreign FOREIGN KEY (administered_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: vaccinations vaccinations_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vaccinations
    ADD CONSTRAINT vaccinations_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE RESTRICT;


--
-- Name: vaccinations vaccinations_health_program_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vaccinations
    ADD CONSTRAINT vaccinations_health_program_id_foreign FOREIGN KEY (health_program_id) REFERENCES public.health_programs(id) ON DELETE SET NULL;


--
-- Name: vet_visits vet_visits_farm_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_farm_id_foreign FOREIGN KEY (farm_id) REFERENCES public.farms(id) ON DELETE CASCADE;


--
-- Name: vet_visits vet_visits_flock_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_flock_id_foreign FOREIGN KEY (flock_id) REFERENCES public.flocks(id) ON DELETE SET NULL;


--
-- Name: vet_visits vet_visits_parent_visit_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_parent_visit_id_foreign FOREIGN KEY (parent_visit_id) REFERENCES public.vet_visits(id) ON DELETE SET NULL;


--
-- Name: vet_visits vet_visits_recorded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_recorded_by_foreign FOREIGN KEY (recorded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: vet_visits vet_visits_veterinarian_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.vet_visits
    ADD CONSTRAINT vet_visits_veterinarian_id_foreign FOREIGN KEY (veterinarian_id) REFERENCES public.veterinarians(id) ON DELETE SET NULL;


--
-- Name: veterinarians veterinarians_organisation_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.veterinarians
    ADD CONSTRAINT veterinarians_organisation_id_foreign FOREIGN KEY (organisation_id) REFERENCES public.organisations(id) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

--
-- PostgreSQL database dump
--

-- Dumped from database version 17.2
-- Dumped by pg_dump version 17.2

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.migrations (id, migration, batch) FROM stdin;
1	0001_01_01_000000_create_users_table	1
2	0001_01_01_000001_create_cache_table	1
3	0001_01_01_000002_create_jobs_table	1
4	2026_02_10_181334_add_role_and_profile_to_users_table	1
5	2026_02_10_181339_create_organisations_table	1
6	2026_02_10_181343_create_organisation_users_table	1
7	2026_02_10_225601_create_countries_table	1
8	2026_02_10_225610_create_cities_table	1
9	2026_02_10_225615_create_chicken_types_table	1
10	2026_02_10_225620_create_farms_table	1
11	2026_02_10_225625_create_buildings_table	1
12	2026_02_10_225630_create_equipments_table	1
13	2026_02_10_231801_add_country_id_and_city_id_to_organisations_table	1
14	2026_02_11_000211_create_plans_table	1
15	2026_02_11_100000_create_platform_roles_and_permissions_tables	1
16	2026_02_11_100001_migrate_users_platform_role_to_fk	1
17	2026_02_11_120000_add_type_to_users_and_create_pivot	1
18	2026_02_12_103451_create_currencies_table	1
19	2026_02_12_103517_add_currency_and_language_to_countries_table	1
20	2026_02_12_110510_remove_owner_id_from_organisations_table	1
21	2026_02_12_133653_add_plan_id_to_organisations_table	1
22	2026_02_12_140001_create_organisation_permissions_table	1
23	2026_02_12_140002_create_organisation_roles_table	1
24	2026_02_12_140003_create_organisation_role_permission_table	1
25	2026_02_12_140004_replace_role_enum_with_fk_in_organisation_users	1
26	2026_02_12_215126_create_farm_organisation_user_table	1
27	2026_02_12_231558_create_suppliers_table	1
28	2026_02_12_231559_create_equipment_movements_table	1
29	2026_02_12_231601_create_equipment_maintenances_table	1
30	2026_02_12_231649_update_equipments_table_add_new_fields	1
31	2026_02_13_184113_create_flocks_table	2
32	2026_02_16_175605_replace_city_country_strings_with_foreign_keys_on_suppliers	3
33	2026_03_27_181438_add_parent_supplier_to_suppliers_table	4
34	2026_03_27_182810_update_flocks_table_v2	5
35	2026_03_28_000001_fix_unique_codes_to_scoped	6
36	2026_03_28_000002_add_constraint_supplier_organisation_or_farm	6
37	2026_03_28_000003_make_organisation_role_id_not_nullable	6
38	2026_03_28_000004_add_soft_deletes_to_organisations_and_farms	6
39	2026_03_28_000005_add_check_constraints_to_equipment_tables	6
40	2026_03_28_000006_add_check_constraints_to_flocks	6
41	2026_03_28_000007_add_farm_id_to_equipments	6
42	2026_03_28_000008_add_performance_indexes	6
43	2026_03_27_204035_add_breed_to_flocks_table	7
44	2026_03_27_210132_create_notifications_table	8
45	2026_03_27_210309_change_notifications_data_to_jsonb	9
46	2026_03_27_212344_create_health_programs_table	10
47	2026_03_28_080233_replace_bird_type_with_chicken_type_id_on_health_programs	11
48	2026_03_28_120000_create_production_phases_table	12
49	2026_03_28_150000_create_daily_records_table	13
50	2026_03_28_160000_create_vaccinations_table	14
51	2026_03_28_170000_create_health_incidents_table	15
52	2026_03_28_180000_create_veterinarians_table	16
53	2026_03_28_190000_create_vet_visits_table	16
54	2026_03_28_200000_create_farm_logs_table	17
55	2026_03_29_100000_create_employees_table	18
56	2026_03_29_100001_create_contracts_table	18
57	2026_03_29_100002_create_leaves_table	18
58	2026_03_29_100003_create_attendances_table	18
59	2026_03_29_100004_create_payrolls_table	18
60	2026_03_29_100005_create_payroll_items_table	18
61	2026_03_29_200000_add_missing_fields_to_employees_table	19
62	2026_03_29_182259_drop_employment_type_from_employees	20
63	2026_03_29_183001_add_id_document_to_employees	21
64	2026_03_29_183812_drop_national_id_number_from_employees	22
65	2026_03_29_184201_add_payment_method_to_employees	23
66	2026_03_29_185720_drop_position_from_employees	24
67	2026_03_29_202855_create_contract_amendments_table	25
68	2026_04_11_135220_add_draft_status_to_contracts_check_constraint	26
69	2026_04_11_142504_add_attachment_to_leaves_table	27
70	2026_04_11_150724_create_accounts_table	28
71	2026_04_11_150728_create_transactions_table	28
72	2026_04_11_150730_create_account_transfers_table	28
73	2026_04_11_165622_create_customers_table	29
74	2026_04_11_165623_create_quotes_table	30
75	2026_04_11_165624_create_quote_items_table	30
76	2026_04_11_165628_create_sales_table	30
77	2026_04_11_165629_create_sale_items_table	30
78	2026_04_11_165631_create_payments_table	30
79	2026_04_11_173023_create_credit_notes_table	31
80	2026_04_11_175941_create_expenses_table	32
81	2026_04_11_203756_create_stock_categories_table	33
82	2026_04_11_203800_create_stocks_table	33
83	2026_04_11_203802_create_stock_lots_table	33
84	2026_04_11_203803_create_stock_movements_table	33
85	2026_04_11_204154_create_purchase_orders_table	33
86	2026_04_11_204156_create_purchase_order_items_table	33
87	2026_04_11_233817_create_stock_inventories_table	34
88	2026_09_19_100000_align_equipment_check_constraints_with_enums	35
89	2026_09_19_100001_add_moov_money_to_payment_method_checks	35
90	2026_09_19_110000_create_payroll_parameters_tables	36
91	2026_09_19_110001_add_fiscal_parts_and_payroll_traceability	36
92	2026_09_19_120000_create_sequences_table	36
93	2026_09_19_130000_add_flock_effectif_and_withdrawal_columns	36
94	2026_09_19_140000_add_applied_and_refunded_amounts_to_credit_notes	37
95	2026_09_19_150000_create_alert_dispatches_table	37
96	2026_09_19_160000_harden_fact_tables_and_add_audit_columns	37
97	2026_09_19_205027_create_activity_log_table	37
98	2026_09_19_205028_add_event_column_to_activity_log_table	37
99	2026_09_19_205029_add_batch_uuid_column_to_activity_log_table	37
\.


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 99, true);


--
-- PostgreSQL database dump complete
--

