import { useState, type FormEvent } from "react"

import { PageShell, ResponsiveGrid, Section } from "@/components/layout"
import {
  Badge,
  Button,
  Card,
  CardContent,
  CardDescription,
  CardFooter,
  CardHeader,
  CardTitle,
  StatCard,
  buttonClasses,
} from "@/components/ui"

type Availability = {
  label: string
  detail: string
}

type PlayArea = {
  departmentId: number
  name: string
  description: string
  entranceFeeTickets: number
  capacity: number
  activeAttendees: number
  availability: Availability
}

type UserSummary = {
  name: string
  role: string
} | null

export type PublicHomeProps = {
  user: UserSummary
  dashboardPath: string | null
  pendingApproval: boolean
  pdoError: string | null
  playAreas: PlayArea[]
}

function statusVariant(label: string) {
  if (label === "Open") {
    return "success" as const
  }
  if (label === "Out Of Order" || label === "Unavailable") {
    return "danger" as const
  }

  return "warning" as const
}

const TRANSACTION_TYPE_LABELS: Record<string, string> = {
  department_admission: "Play-area admission",
  department_payout: "Play-area payout",
  gift_shop_redemption: "Gift shop redemption",
  gift_shop_inventory_procurement: "Gift shop stocking",
  gift_shop_inventory_credit: "Gift shop inventory credit",
  owner_allocation: "Owner allocation",
  owner_generated_transfer: "Owner transfer",
  owner_investment: "Owner investment",
  member_claim_transfer: "Session claim to wallet",
  manual_override: "Manual adjustment",
}

type AuthMode = "login" | "register"

type WalletTransactionRow = {
  ticketTransactionId: number
  transactionType: string
  amount: number
  delta: number
  note: string | null
  createdAt: string
  departmentName: string | null
}

type WalletLookupResponse =
  | {
      ok: true
      member: { name: string; membershipCode: string }
      walletAccountId: number | null
      balance: number
      transactions: WalletTransactionRow[]
    }
  | {
      ok: false
      message: string
    }

type AuthResponse = {
  ok: boolean
  message: string
  user?: {
    name: string
    role: string
  }
  dashboardPath?: string
}

export function PublicHome({
  user,
  dashboardPath,
  pendingApproval,
  pdoError,
  playAreas,
}: PublicHomeProps) {
  const [isLoginOpen, setIsLoginOpen] = useState(false)
  const [authMode, setAuthMode] = useState<AuthMode>("login")
  const [authMessage, setAuthMessage] = useState<string | null>(null)
  const [authError, setAuthError] = useState<string | null>(null)
  const [loggedInUser, setLoggedInUser] = useState<UserSummary>(user)
  const [activeDashboardPath, setActiveDashboardPath] = useState<string | null>(dashboardPath)
  const [walletCode, setWalletCode] = useState("")
  const [walletLoading, setWalletLoading] = useState(false)
  const [walletError, setWalletError] = useState<string | null>(null)
  const [walletData, setWalletData] = useState<Extract<WalletLookupResponse, { ok: true }> | null>(
    null,
  )
  const activeCount = playAreas.reduce((total, area) => total + area.activeAttendees, 0)
  const capacityCount = playAreas.reduce((total, area) => total + area.capacity, 0)

  async function handleAuthSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    const formData = new FormData(event.currentTarget)
    setAuthError(null)
    setAuthMessage(null)

    const response = await fetch("auth_modal.php", {
      method: "POST",
      body: formData,
      credentials: "same-origin",
    })
    const result = (await response.json()) as AuthResponse

    if (!response.ok || !result.ok) {
      setAuthError(result.message || "Something went wrong.")
      return
    }

    setAuthMessage(result.message)
    if (result.user) {
      setLoggedInUser(result.user)
      setActiveDashboardPath(result.dashboardPath ?? "index.php")
      setIsLoginOpen(false)
      window.location.href = result.dashboardPath ?? "index.php"
    }
  }

  function openAuthModal(mode: AuthMode) {
    setAuthMode(mode)
    setAuthError(null)
    setAuthMessage(null)
    setIsLoginOpen(true)
  }

  function transactionTypeLabel(type: string) {
    return TRANSACTION_TYPE_LABELS[type] ?? type.replace(/_/g, " ")
  }

  function formatWalletDelta(delta: number) {
    if (delta > 0) {
      return `+${delta.toLocaleString()}`
    }
    if (delta < 0) {
      return delta.toLocaleString()
    }
    return "—"
  }

  async function handleWalletLookup(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const code = walletCode.trim()
    setWalletError(null)
    setWalletData(null)
    if (code === "") {
      setWalletError("Enter your membership code.")
      return
    }
    setWalletLoading(true)
    const formData = new FormData()
    formData.set("membership_code", code)
    try {
      const response = await fetch("member_wallet_lookup.php", {
        method: "POST",
        body: formData,
        credentials: "same-origin",
      })
      const result = (await response.json()) as WalletLookupResponse
      if (!result.ok) {
        setWalletError(result.message || "Could not look up that membership code.")
        return
      }
      setWalletData(result)
    } catch {
      setWalletError("Network error. Please try again.")
    } finally {
      setWalletLoading(false)
    }
  }

  function clearWalletView() {
    setWalletData(null)
    setWalletError(null)
    setWalletCode("")
  }

  return (
    <>
      <PageShell
        actions={
          loggedInUser ? (
            <>
              {activeDashboardPath && !pendingApproval ? (
                <a className={buttonClasses()} href={activeDashboardPath}>
                  Go to Dashboard
                </a>
              ) : null}
              <a className={buttonClasses({ variant: "neutral" })} href="logout.php">
                Logout
              </a>
            </>
          ) : (
            <Button onClick={() => openAuthModal("login")}>Staff Login</Button>
          )
        }
        description="Check live play-area availability before you head to the counter."
        eyebrow="Arcade Management"
        headerClassName="mx-auto w-full max-w-xl text-center lg:grid-cols-1"
        title="kewl Arcade"
        titleClassName="kewl-title mx-auto text-5xl font-black uppercase sm:text-7xl"
      >

      <section>
        <Card className="bg-main">
          <CardHeader>
            <CardTitle className="text-3xl text-main-foreground">
              Live Department Board
            </CardTitle>
            <CardDescription className="text-main-foreground">
              Entry prices are listed in tickets, and attendance updates from staff sessions.
            </CardDescription>
          </CardHeader>
          <CardContent className="grid gap-4 sm:grid-cols-3">
            <StatCard
              className="bg-secondary-background"
              detail="Configured play areas"
              label="Areas"
              value={playAreas.length}
            />
            <StatCard
              className="bg-secondary-background"
              detail="Current attendance"
              label="Players"
              value={activeCount}
            />
            <StatCard
              className="bg-secondary-background"
              detail="Total play-area capacity"
              label="Capacity"
              value={capacityCount}
            />
          </CardContent>
          <CardFooter className="text-main-foreground">
            {loggedInUser ? (
              <p className="m-0">
                Logged in as <strong>{loggedInUser.name}</strong> ({loggedInUser.role}).
              </p>
            ) : (
              <p className="m-0">Staff can log in to manage dashboards and ticket activity.</p>
            )}
            {pendingApproval ? (
              <Badge variant="accent">Account pending approval</Badge>
            ) : null}
          </CardFooter>
        </Card>
      </section>

      <section>
        <Card>
          <CardContent className="grid gap-4">
            <form className="flex flex-wrap items-end gap-3" onSubmit={handleWalletLookup}>
              <label className="grid min-w-[12rem] flex-1 gap-1 font-heading text-sm" htmlFor="wallet-membership-code">
                Membership code
                <input
                  autoComplete="off"
                  className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                  id="wallet-membership-code"
                  name="membership_code"
                  onChange={(e) => setWalletCode(e.target.value)}
                  type="text"
                  value={walletCode}
                />
              </label>
              <Button disabled={walletLoading} type="submit">
                {walletLoading ? "Looking up…" : "Look up"}
              </Button>
              {walletData ? (
                <Button onClick={clearWalletView} type="button" variant="neutral">
                  Clear
                </Button>
              ) : null}
            </form>
            {walletError ? (
              <div className="rounded-base border-2 border-border bg-danger p-3 text-sm font-heading text-main-foreground">
                {walletError}
              </div>
            ) : null}
            {walletData ? (
              <div className="grid gap-4">
                <div className="rounded-base border-2 border-border bg-accent p-4 text-accent-foreground">
                  <p className="m-0 text-xs font-heading uppercase tracking-wide">Member</p>
                  <p className="m-0 text-lg font-heading">{walletData.member.name}</p>
                  <p className="m-0 mt-3 text-xs font-heading uppercase tracking-wide">
                    Tickets in wallet
                  </p>
                  <p className="m-0 text-3xl font-heading">
                    {walletData.balance.toLocaleString()}
                  </p>
                </div>
                <div>
                  <p className="m-0 mb-2 font-heading text-sm uppercase tracking-wide text-muted-foreground">
                    Recent transactions
                  </p>
                  {walletData.transactions.length === 0 ? (
                    <p className="m-0 text-sm text-muted-foreground">No transactions yet.</p>
                  ) : (
                    <div className="overflow-x-auto rounded-base border-2 border-border">
                      <table className="w-full min-w-[36rem] border-collapse text-left text-sm">
                        <thead>
                          <tr className="border-b-2 border-border bg-secondary-background font-heading">
                            <th className="px-3 py-2">When</th>
                            <th className="px-3 py-2">Type</th>
                            <th className="px-3 py-2 text-right">Change</th>
                            <th className="px-3 py-2">Note</th>
                          </tr>
                        </thead>
                        <tbody>
                          {walletData.transactions.map((tx) => (
                            <tr className="border-b border-border last:border-b-0" key={tx.ticketTransactionId}>
                              <td className="px-3 py-2 whitespace-nowrap align-top">
                                {new Date(tx.createdAt).toLocaleString()}
                              </td>
                              <td className="px-3 py-2 align-top">
                                <span className="block">{transactionTypeLabel(tx.transactionType)}</span>
                                {tx.departmentName ? (
                                  <span className="text-muted-foreground">{tx.departmentName}</span>
                                ) : null}
                              </td>
                              <td className="px-3 py-2 text-right font-heading align-top whitespace-nowrap">
                                {formatWalletDelta(tx.delta)}
                              </td>
                              <td className="px-3 py-2 align-top text-muted-foreground">
                                {tx.note ?? "—"}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </div>
              </div>
            ) : null}
          </CardContent>
        </Card>
      </section>

      <Section
        title="Play Areas"
      >
        {pdoError ? (
          <Card className="bg-danger text-main-foreground">
            <CardTitle>Live availability unavailable</CardTitle>
            <CardDescription className="text-main-foreground">{pdoError}</CardDescription>
          </Card>
        ) : playAreas.length === 0 ? (
          <Card>
            <CardTitle>No play areas configured yet.</CardTitle>
            <CardDescription>
              Once departments are created, public availability will appear here.
            </CardDescription>
          </Card>
        ) : (
          <ResponsiveGrid>
            {playAreas.map((area) => (
              <Card className="relative pt-14" key={area.departmentId}>
                <Badge
                  className="absolute right-4 top-4"
                  variant={statusVariant(area.availability.label)}
                >
                  {area.availability.label}
                </Badge>
                <CardHeader>
                  <CardTitle>{area.name}</CardTitle>
                  <CardDescription>{area.description || "Arcade play area"}</CardDescription>
                </CardHeader>
                <CardContent>
                  <div className="grid grid-cols-2 gap-3">
                    <div className="rounded-base border-2 border-border bg-main p-3 text-main-foreground">
                      <p className="m-0 text-xs font-heading uppercase tracking-wide">Entry</p>
                      <p className="m-0 text-2xl font-heading">
                        {area.entranceFeeTickets.toLocaleString()} tickets
                      </p>
                    </div>
                    <div className="rounded-base border-2 border-border bg-accent p-3 text-accent-foreground">
                      <p className="m-0 text-xs font-heading uppercase tracking-wide">
                        Attendance
                      </p>
                      <p className="m-0 text-2xl font-heading">
                        {area.activeAttendees.toLocaleString()} /{" "}
                        {area.capacity.toLocaleString()}
                      </p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))}
          </ResponsiveGrid>
        )}
      </Section>

      <footer className="flex flex-wrap items-center justify-between gap-3 pb-6 text-sm">
        <a className="font-heading underline" href="index.php?db_test=1">
          DB smoke test
        </a>
      </footer>
    </PageShell>
      {isLoginOpen ? (
        <div
          aria-labelledby="staff-login-title"
          aria-modal="true"
          className="fixed inset-0 z-50 grid place-items-center bg-overlay p-4"
          role="dialog"
        >
          <div className="w-full max-w-md rounded-base border-2 border-border bg-secondary-background p-6 shadow-smash">
            <div className="mb-5 flex items-start justify-between gap-4">
              <div>
                <h2 className="m-0 text-3xl" id="staff-login-title">
                  {authMode === "login" ? "Staff Login" : "Request Account"}
                </h2>
                <p className="m-0 mt-1 text-sm text-muted-foreground">
                  {authMode === "login"
                    ? "Sign in to continue to your dashboard."
                    : "Send an account request to the admin approval queue."}
                </p>
              </div>
              <Button
                aria-label="Close login popup"
                onClick={() => setIsLoginOpen(false)}
                size="sm"
                variant="neutral"
              >
                Close
              </Button>
            </div>
            {authError ? (
              <div className="mb-4 rounded-base border-2 border-border bg-danger p-3 text-sm font-heading text-main-foreground">
                {authError}
              </div>
            ) : null}
            {authMessage ? (
              <div className="mb-4 rounded-base border-2 border-border bg-success p-3 text-sm font-heading text-main-foreground">
                {authMessage}
                {loggedInUser ? (
                  <div className="mt-2">
                    Logged in as <strong>{loggedInUser.name}</strong>.
                  </div>
                ) : null}
              </div>
            ) : null}
            <form className="grid gap-4" method="post" onSubmit={handleAuthSubmit}>
                <input name="action" type="hidden" value={authMode} />
                {authMode === "register" ? (
                  <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-name">
                    Name
                    <input
                      className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                      id="home-register-name"
                      name="name"
                      required
                      type="text"
                    />
                  </label>
                ) : null}
                <label className="grid gap-1 font-heading text-sm" htmlFor="home-auth-email">
                  Email
                  <input
                    className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    id="home-auth-email"
                    name="email"
                    required
                    type="email"
                  />
                </label>
                <label className="grid gap-1 font-heading text-sm" htmlFor="home-auth-password">
                  Password
                  <input
                    className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    id="home-auth-password"
                    name="password"
                    required
                    type="password"
                  />
                </label>
                {authMode === "register" ? (
                  <>
                    <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-confirm">
                      Confirm Password
                      <input
                        className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        id="home-register-confirm"
                        name="confirm_password"
                        required
                        type="password"
                      />
                    </label>
                    <label className="grid gap-1 font-heading text-sm" htmlFor="home-register-role">
                      Role
                      <select
                        className="h-10 rounded-base border-2 border-border bg-secondary-background px-3 font-sans text-sm font-base focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        id="home-register-role"
                        name="role"
                        required
                      >
                        <option value="employee">Employee</option>
                        <option value="owner">Owner</option>
                        <option value="hr">HR</option>
                      </select>
                    </label>
                  </>
                ) : null}
                <Button type="submit">
                  {authMode === "register" ? "Send Account Request" : "Sign In"}
                </Button>
                <Button
                  onClick={() => openAuthModal(authMode === "register" ? "login" : "register")}
                  type="button"
                  variant="neutral"
                >
                  {authMode === "register" ? "Back To Staff Login" : "Create Staff Account"}
                </Button>
              </form>
          </div>
        </div>
      ) : null}
    </>
  )
}
