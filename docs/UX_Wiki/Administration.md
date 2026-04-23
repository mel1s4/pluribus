# System Administration Practices

This page defines practical administration standards for the platform's admin
dashboard. It focuses on three things:

- What system administrators need to see and do
- How access is managed safely
- Which pages/options are needed to operate the system effectively

## Core Operating Principles

- Least privilege by default (only minimum required access)
- Auditability for all privileged actions
- Security-first controls for sensitive operations
- Safe change management (preview, staged rollout, rollback)
- Actionable alerts with ownership and runbooks

## Admin Dashboard Information Architecture

### 1) Dashboard Overview

Primary "mission control" page with:

- Service health status and uptime
- Active incidents and alert severity
- Latency/error trends
- Backup success/failure summary
- Capacity pressure indicators (CPU, memory, storage)
- Security alerts and policy violations

### 2) Monitoring

- Real-time metrics by service/component
- SLO/SLA tracking and burn-rate indicators
- Dependency map (upstream/downstream impact)
- Alert routing and notification policies

### 3) Logs and Audit

- Searchable application/system logs
- Structured filters (time, service, environment, user, severity)
- Immutable audit trail for admin actions
- Export and retention controls

### 4) Backup and Restore

- Backup schedules and policy definitions
- Last successful backup by system/domain
- Restore point catalog
- Restore simulation and full restore workflow

### 5) Recovery and Disaster Readiness

- Failover controls and recovery playbooks
- RTO/RPO target visibility
- Disaster recovery drill history
- Post-recovery validation checklist

### 6) Security Center

- Authentication and MFA policy status
- Key/certificate lifecycle management
- Vulnerability exposure dashboard
- Suspicious activity and lockout events
- Compliance control mapping

### 7) Performance

- Application performance metrics (latency, throughput, errors)
- Slow operations/jobs/query visibility
- Bottleneck detection and recommendations
- Trend comparison by release/version

### 8) Capacity and Scalability

- Resource utilization by environment
- Forecasting and growth projections
- Quota management and exhaustion warnings
- Autoscaling policy configuration

### 9) Access Management (IAM)

- Users, groups, and service accounts
- Role assignment and scoped permissions
- Temporary elevation (just-in-time access)
- Access request and approval workflows
- Access review and certification reports

### 10) Change and Configuration Management

- Feature flags and release gates
- Environment configuration controls
- Change history with before/after snapshots
- Rollback controls for failed or risky changes

### 11) Incident Management

- Incident lifecycle tracking (open, triage, mitigate, resolve)
- Timeline and owner assignment
- Communication templates for internal/external updates
- Postmortem action tracking

### 12) Integrations and Automation

- Integrations (SIEM, pager, chatops, email)
- Webhook and API token management
- Automation jobs/schedulers and execution status

## Access Management Model

### Authentication

- Require SSO (OIDC/SAML) for administrator access
- Enforce MFA for all privileged roles
- Re-authentication for critical operations

### Authorization

- Use RBAC with optional scope constraints by environment/tenant/region
- Apply separation of duties for high-risk actions
- Use dual approval for destructive operations in production

### Access Lifecycle

- Joiner: approved role assignment with scoped defaults
- Mover: role updates based on responsibility changes
- Leaver: immediate deprovisioning and token revocation
- Quarterly access reviews for all privileged accounts

### Emergency Access

- Break-glass accounts allowed only for incidents
- Session recording and mandatory post-incident review

## Recommended Role Set

- Viewer: read-only dashboards, logs, and reports
- Operator: run operational tasks and incident actions
- Security Admin: manage IAM/security policy and investigations
- System Admin: broad platform operations and configuration
- Super Admin: full access, limited membership, additional approval controls

## Required Guardrails for Dangerous Actions

For actions like deleting data, changing auth settings, disabling controls, or
failing over production:

- Explicit warning + impact summary
- Confirmation step (re-auth where required)
- Optional second approver for production
- Full audit log with actor, time, reason, and affected scope
- One-click rollback (where technically feasible)

## UX Best Practices for Admin Pages

- Keep critical status above the fold
- Make alerts actionable (owner + next step + runbook link)
- Provide powerful filtering and saved views
- Favor progressive disclosure for advanced options
- Support bulk operations with safe preview
- Include context help for high-risk settings

## Operational Metrics to Track

- MTTD (mean time to detect)
- MTTR (mean time to recover)
- Backup success rate and restore success rate
- Privileged access review completion rate
- Number of policy violations by severity
- Capacity forecast accuracy

## Minimum MVP for Administration

If shipping in phases, start with:

1. Dashboard Overview
2. Monitoring
3. Logs and Audit
4. Access Management
5. Backup and Restore
6. Incident Management

Then expand into advanced recovery, capacity forecasting, and deeper automation.